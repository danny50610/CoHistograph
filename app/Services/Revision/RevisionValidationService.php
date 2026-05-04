<?php

namespace App\Services\Revision;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\RevisionAction;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Support\Facades\DB;

class RevisionValidationService
{
    /**
     * @var array<string, VertexType>
     */
    private array $vertexTypeByLabel = [];

    /**
     * @var array<string, EdgeType>
     */
    private array $edgeTypeByLabel = [];

    /**
     * @var array<int, array{exists:bool,type_label:string,properties:array<string,bool>}>
     */
    private array $ageVertexCache = [];

    /**
     * @var array<int, array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>}>
     */
    private array $ageEdgeCache = [];

    /**
     * @var array<string, array{exists:bool,type_label:string,properties:array<string,bool>}>
     */
    private array $vertexStates = [];

    /**
     * @var array<string, array{exists:bool,type_label:string,start_key:string,end_key:string,properties:array<string,bool>}>
     */
    private array $edgeStates = [];

    /**
     * @var array<int, RevisionAction>
     */
    private array $actionByOrder = [];

    /**
     * @var array<int, bool>
     */
    private array $actionHasError = [];

    public function validate(Revision $revision): RevisionValidationResult
    {
        $this->bootSchemaMaps();

        $result = new RevisionValidationResult;
        $actions = $revision->actions()
            ->orderBy('order')
            ->get();

        if ($actions->isEmpty()) {
            $result->addGeneralError('至少需要一筆操作才能提交審核');

            return $result;
        }

        foreach ($actions as $expectedOrder => $action) {
            $this->actionByOrder[(int) $action->order] = $action;

            if ((int) $action->order !== $expectedOrder) {
                $result->addActionError(
                    (int) $action->order,
                    'ORDER_NOT_CONTIGUOUS',
                    '操作順序不連續，請重新整理後儲存草稿',
                    ['expected_order' => $expectedOrder],
                );
                $this->actionHasError[(int) $action->order] = true;
            }
        }

        foreach ($actions as $action) {
            $order = (int) $action->order;
            $this->actionHasError[$order] = $this->actionHasError[$order] ?? false;

            $this->validateSingleAction($action, $result);
        }

        return $result;
    }

    private function validateSingleAction(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $actionType = $action->action;
        if (! $actionType instanceof RevisionActionType) {
            $this->addActionError($result, $order, 'INVALID_ACTION_TYPE', '不支援的操作類型');

            return;
        }

        $this->validateAllowedFields($action, $result);

        if ($this->actionHasError($order)) {
            return;
        }

        switch ($actionType) {
            case RevisionActionType::CreateVertex:
                $this->validateCreateVertex($action, $result);
                break;
            case RevisionActionType::DeleteVertex:
                $this->validateDeleteVertex($action, $result);
                break;
            case RevisionActionType::CreateEdge:
                $this->validateCreateEdge($action, $result);
                break;
            case RevisionActionType::DeleteEdge:
                $this->validateDeleteEdge($action, $result);
                break;
            case RevisionActionType::CreateVertexProperty:
                $this->validateVertexPropertyAction($action, $result, true, false);
                break;
            case RevisionActionType::UpdateVertexProperty:
                $this->validateVertexPropertyAction($action, $result, false, false);
                break;
            case RevisionActionType::DeleteVertexProperty:
                $this->validateVertexPropertyAction($action, $result, false, true);
                break;
            case RevisionActionType::CreateEdgeProperty:
                $this->validateEdgePropertyAction($action, $result, true, false);
                break;
            case RevisionActionType::UpdateEdgeProperty:
                $this->validateEdgePropertyAction($action, $result, false, false);
                break;
            case RevisionActionType::DeleteEdgeProperty:
                $this->validateEdgePropertyAction($action, $result, false, true);
                break;
        }
    }

    private function validateCreateVertex(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $label = $action->vertex_type_label;

        if (! is_string($label) || $label === '') {
            $this->addActionError($result, $order, 'MISSING_VERTEX_TYPE', '新增 Vertex 需要指定 vertex_type_label');

            return;
        }

        if (! isset($this->vertexTypeByLabel[$label])) {
            $this->addActionError($result, $order, 'VERTEX_TYPE_NOT_FOUND', "找不到 Vertex 類型: {$label}");

            return;
        }

        $this->vertexStates[$this->refVertexKey($order)] = [
            'exists' => true,
            'type_label' => $label,
            'properties' => [],
        ];
    }

    private function validateDeleteVertex(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $target = $this->resolveVertexTarget($action, $result, 'delete_vertex');
        if ($target === null) {
            return;
        }

        if (! $this->vertexExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Vertex 不存在');

            return;
        }

        $remainingEdges = $this->findRemainingConnectedEdgeKeys($target['key']);
        if ($remainingEdges !== []) {
            $humanList = implode(', ', array_map(function (string $edgeKey): string {
                if (str_starts_with($edgeKey, 'age:')) {
                    return 'Edge ID:'.substr($edgeKey, 4);
                }

                return '操作 #'.((int) substr($edgeKey, 4) + 1);
            }, $remainingEdges));

            $this->addActionError(
                $result,
                $order,
                'VERTEX_HAS_REMAINING_EDGES',
                '目標 Vertex 仍有未刪除的關聯 Edge: '.$humanList,
                ['remaining_edges' => $remainingEdges],
            );

            return;
        }

        $this->markVertexDeleted($target['key']);
    }

    private function validateCreateEdge(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $edgeLabel = $action->edge_type_label;
        if (! is_string($edgeLabel) || $edgeLabel === '') {
            $this->addActionError($result, $order, 'MISSING_EDGE_TYPE', '新增 Edge 需要指定 edge_type_label');

            return;
        }

        $edgeType = $this->edgeTypeByLabel[$edgeLabel] ?? null;
        if ($edgeType === null) {
            $this->addActionError($result, $order, 'EDGE_TYPE_NOT_FOUND', "找不到 Edge 類型: {$edgeLabel}");

            return;
        }

        $start = $this->resolveStartVertex($action, $result);
        $end = $this->resolveEndVertex($action, $result);
        if ($start === null || $end === null) {
            return;
        }

        if (! $this->vertexExists($start['key'])) {
            $this->addActionError($result, $order, 'START_VERTEX_NOT_FOUND', '起始 Vertex 不存在');
        }

        if (! $this->vertexExists($end['key'])) {
            $this->addActionError($result, $order, 'END_VERTEX_NOT_FOUND', '終止 Vertex 不存在');
        }

        if ($this->actionHasError($order)) {
            return;
        }

        $expectedStart = $edgeType->startVertex?->age_label_name;
        $expectedEnd = $edgeType->endVertex?->age_label_name;
        $actualStart = $this->vertexTypeLabel($start['key']);
        $actualEnd = $this->vertexTypeLabel($end['key']);

        if ($actualStart !== $expectedStart || $actualEnd !== $expectedEnd) {
            $this->addActionError(
                $result,
                $order,
                'EDGE_VERTEX_TYPE_MISMATCH',
                '起訖 Vertex 類型不符合 Edge 類型定義',
                [
                    'expected_start' => $expectedStart,
                    'expected_end' => $expectedEnd,
                    'actual_start' => $actualStart,
                    'actual_end' => $actualEnd,
                ],
            );

            return;
        }

        $this->edgeStates[$this->refEdgeKey($order)] = [
            'exists' => true,
            'type_label' => $edgeLabel,
            'start_key' => $start['key'],
            'end_key' => $end['key'],
            'properties' => [],
        ];
    }

    private function validateDeleteEdge(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $target = $this->resolveEdgeTarget($action, $result, 'delete_edge');
        if ($target === null) {
            return;
        }

        if (! $this->edgeExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Edge 不存在');

            return;
        }

        $this->markEdgeDeleted($target['key']);
    }

    private function validateVertexPropertyAction(
        RevisionAction $action,
        RevisionValidationResult $result,
        bool $isCreate,
        bool $isDelete,
    ): void {
        $order = (int) $action->order;
        $target = $this->resolveVertexTarget($action, $result, $action->action->value);
        if ($target === null) {
            return;
        }

        if (! $this->vertexExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Vertex 不存在');

            return;
        }

        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            $this->addActionError($result, $order, 'MISSING_PROPERTY_NAME', '需要指定 age_property_name');

            return;
        }

        $vertexTypeLabel = $this->vertexTypeLabel($target['key']);
        $vertexType = $vertexTypeLabel ? ($this->vertexTypeByLabel[$vertexTypeLabel] ?? null) : null;
        $property = $vertexType?->properties->firstWhere('age_property_name', $propertyName);

        if ($property === null) {
            $this->addActionError($result, $order, 'PROPERTY_NOT_FOUND', '指定的 Vertex 屬性不存在');

            return;
        }

        $hasProperty = $this->hasVertexProperty($target['key'], $propertyName);
        if ($isCreate && $hasProperty) {
            $this->addActionError($result, $order, 'PROPERTY_ALREADY_EXISTS', '目標 Vertex 上該屬性已有值');

            return;
        }

        if (! $isCreate && ! $hasProperty) {
            $this->addActionError($result, $order, 'PROPERTY_NOT_EXISTS', '目標 Vertex 上該屬性尚無值');

            return;
        }

        if ($isDelete) {
            if (! is_null($action->value)) {
                $this->addActionError($result, $order, 'DELETE_VALUE_MUST_BE_NULL', '刪除屬性時 value 必須為 null');

                return;
            }

            $this->setVertexPropertyExists($target['key'], $propertyName, false);

            return;
        }

        if (is_null($action->value)) {
            $this->addActionError($result, $order, 'MISSING_VALUE', '建立或更新屬性時需要提供 value');

            return;
        }

        if (! $this->valueMatchesType($action->value, $property->age_property_type)) {
            $this->addActionError(
                $result,
                $order,
                'PROPERTY_TYPE_MISMATCH',
                '屬性值型別不符合定義',
                ['expected_type' => $property->age_property_type->value],
            );

            return;
        }

        $this->setVertexPropertyExists($target['key'], $propertyName, true);
    }

    private function validateEdgePropertyAction(
        RevisionAction $action,
        RevisionValidationResult $result,
        bool $isCreate,
        bool $isDelete,
    ): void {
        $order = (int) $action->order;
        $target = $this->resolveEdgeTarget($action, $result, $action->action->value);
        if ($target === null) {
            return;
        }

        if (! $this->edgeExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Edge 不存在');

            return;
        }

        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            $this->addActionError($result, $order, 'MISSING_PROPERTY_NAME', '需要指定 age_property_name');

            return;
        }

        $edgeTypeLabel = $this->edgeTypeLabel($target['key']);
        $edgeType = $edgeTypeLabel ? ($this->edgeTypeByLabel[$edgeTypeLabel] ?? null) : null;
        $property = $edgeType?->properties->firstWhere('age_property_name', $propertyName);

        if ($property === null) {
            $this->addActionError($result, $order, 'PROPERTY_NOT_FOUND', '指定的 Edge 屬性不存在');

            return;
        }

        $hasProperty = $this->hasEdgeProperty($target['key'], $propertyName);
        if ($isCreate && $hasProperty) {
            $this->addActionError($result, $order, 'PROPERTY_ALREADY_EXISTS', '目標 Edge 上該屬性已有值');

            return;
        }

        if (! $isCreate && ! $hasProperty) {
            $this->addActionError($result, $order, 'PROPERTY_NOT_EXISTS', '目標 Edge 上該屬性尚無值');

            return;
        }

        if ($isDelete) {
            if (! is_null($action->value)) {
                $this->addActionError($result, $order, 'DELETE_VALUE_MUST_BE_NULL', '刪除屬性時 value 必須為 null');

                return;
            }

            $this->setEdgePropertyExists($target['key'], $propertyName, false);

            return;
        }

        if (is_null($action->value)) {
            $this->addActionError($result, $order, 'MISSING_VALUE', '建立或更新屬性時需要提供 value');

            return;
        }

        if (! $this->valueMatchesType($action->value, $property->age_property_type)) {
            $this->addActionError(
                $result,
                $order,
                'PROPERTY_TYPE_MISMATCH',
                '屬性值型別不符合定義',
                ['expected_type' => $property->age_property_type->value],
            );

            return;
        }

        $this->setEdgePropertyExists($target['key'], $propertyName, true);
    }

    private function validateAllowedFields(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $actionType = $action->action;
        if (! $actionType instanceof RevisionActionType) {
            return;
        }

        $allowed = match ($actionType) {
            RevisionActionType::CreateVertex => ['vertex_type_label'],
            RevisionActionType::DeleteVertex => ['target_age_id', 'target_ref_order'],
            RevisionActionType::CreateEdge => [
                'edge_type_label',
                'start_vertex_age_id',
                'start_vertex_ref_order',
                'end_vertex_age_id',
                'end_vertex_ref_order',
            ],
            RevisionActionType::DeleteEdge => ['target_age_id', 'target_ref_order'],
            RevisionActionType::CreateVertexProperty,
            RevisionActionType::UpdateVertexProperty => ['target_age_id', 'target_ref_order', 'age_property_name', 'value'],
            RevisionActionType::DeleteVertexProperty => ['target_age_id', 'target_ref_order', 'age_property_name'],
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty => ['target_age_id', 'target_ref_order', 'age_property_name', 'value'],
            RevisionActionType::DeleteEdgeProperty => ['target_age_id', 'target_ref_order', 'age_property_name'],
        };

        $fieldValues = [
            'target_age_id' => $action->target_age_id,
            'target_ref_order' => $action->target_ref_order,
            'vertex_type_label' => $action->vertex_type_label,
            'edge_type_label' => $action->edge_type_label,
            'start_vertex_age_id' => $action->start_vertex_age_id,
            'start_vertex_ref_order' => $action->start_vertex_ref_order,
            'end_vertex_age_id' => $action->end_vertex_age_id,
            'end_vertex_ref_order' => $action->end_vertex_ref_order,
            'age_property_name' => $action->age_property_name,
            'value' => $action->value,
        ];

        foreach ($fieldValues as $field => $value) {
            if (in_array($field, $allowed, true)) {
                continue;
            }

            if ($this->isProvided($value)) {
                $this->addActionError(
                    $result,
                    $order,
                    'UNEXPECTED_FIELD',
                    "此操作不應提供欄位: {$field}",
                    ['field' => $field],
                );
            }
        }

        if ($actionType === RevisionActionType::DeleteVertex || $actionType === RevisionActionType::DeleteEdge
            || str_contains($actionType->value, '_property')) {
            $this->validateExclusiveReference(
                $result,
                $order,
                $action->target_age_id,
                $action->target_ref_order,
                'target_age_id',
                'target_ref_order',
            );
        }

        if ($actionType === RevisionActionType::CreateEdge) {
            $this->validateExclusiveReference(
                $result,
                $order,
                $action->start_vertex_age_id,
                $action->start_vertex_ref_order,
                'start_vertex_age_id',
                'start_vertex_ref_order',
            );

            $this->validateExclusiveReference(
                $result,
                $order,
                $action->end_vertex_age_id,
                $action->end_vertex_ref_order,
                'end_vertex_age_id',
                'end_vertex_ref_order',
            );
        }
    }

    private function validateExclusiveReference(
        RevisionValidationResult $result,
        int $order,
        mixed $ageId,
        mixed $refOrder,
        string $ageField,
        string $refField,
    ): void {
        $hasAgeId = $this->isProvided($ageId);
        $hasRefOrder = $this->isProvided($refOrder);

        if ($hasAgeId === $hasRefOrder) {
            $this->addActionError(
                $result,
                $order,
                'REFERENCE_MUTUAL_EXCLUSIVE',
                "{$ageField} 與 {$refField} 必須且只能提供其中一個",
                ['age_field' => $ageField, 'ref_field' => $refField],
            );
        }
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    private function resolveVertexTarget(RevisionAction $action, RevisionValidationResult $result, string $context): ?array
    {
        $order = (int) $action->order;
        if (! is_null($action->target_ref_order)) {
            $refOrder = (int) $action->target_ref_order;
            $dependency = $this->validateReferenceDependency($result, $order, $refOrder, RevisionActionType::CreateVertex);
            if (! $dependency) {
                return null;
            }

            return [
                'key' => $this->refVertexKey($refOrder),
                'is_ref' => true,
            ];
        }

        if (is_null($action->target_age_id)) {
            $this->addActionError($result, $order, 'MISSING_TARGET', "{$context} 需要指定 target");

            return null;
        }

        $ageId = (int) $action->target_age_id;
        $this->loadAgeVertexState($ageId);

        return [
            'key' => $this->ageVertexKey($ageId),
            'is_ref' => false,
        ];
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    private function resolveEdgeTarget(RevisionAction $action, RevisionValidationResult $result, string $context): ?array
    {
        $order = (int) $action->order;
        if (! is_null($action->target_ref_order)) {
            $refOrder = (int) $action->target_ref_order;
            $dependency = $this->validateReferenceDependency($result, $order, $refOrder, RevisionActionType::CreateEdge);
            if (! $dependency) {
                return null;
            }

            return [
                'key' => $this->refEdgeKey($refOrder),
                'is_ref' => true,
            ];
        }

        if (is_null($action->target_age_id)) {
            $this->addActionError($result, $order, 'MISSING_TARGET', "{$context} 需要指定 target");

            return null;
        }

        $ageId = (int) $action->target_age_id;
        $this->loadAgeEdgeState($ageId);

        return [
            'key' => $this->ageEdgeKey($ageId),
            'is_ref' => false,
        ];
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    private function resolveStartVertex(RevisionAction $action, RevisionValidationResult $result): ?array
    {
        $order = (int) $action->order;

        if (! is_null($action->start_vertex_ref_order)) {
            $refOrder = (int) $action->start_vertex_ref_order;
            $dependency = $this->validateReferenceDependency($result, $order, $refOrder, RevisionActionType::CreateVertex);
            if (! $dependency) {
                return null;
            }

            return ['key' => $this->refVertexKey($refOrder), 'is_ref' => true];
        }

        if (is_null($action->start_vertex_age_id)) {
            $this->addActionError($result, $order, 'MISSING_START_VERTEX', 'create_edge 需要指定起始 Vertex');

            return null;
        }

        $ageId = (int) $action->start_vertex_age_id;
        $this->loadAgeVertexState($ageId);

        return ['key' => $this->ageVertexKey($ageId), 'is_ref' => false];
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    private function resolveEndVertex(RevisionAction $action, RevisionValidationResult $result): ?array
    {
        $order = (int) $action->order;

        if (! is_null($action->end_vertex_ref_order)) {
            $refOrder = (int) $action->end_vertex_ref_order;
            $dependency = $this->validateReferenceDependency($result, $order, $refOrder, RevisionActionType::CreateVertex);
            if (! $dependency) {
                return null;
            }

            return ['key' => $this->refVertexKey($refOrder), 'is_ref' => true];
        }

        if (is_null($action->end_vertex_age_id)) {
            $this->addActionError($result, $order, 'MISSING_END_VERTEX', 'create_edge 需要指定終止 Vertex');

            return null;
        }

        $ageId = (int) $action->end_vertex_age_id;
        $this->loadAgeVertexState($ageId);

        return ['key' => $this->ageVertexKey($ageId), 'is_ref' => false];
    }

    private function validateReferenceDependency(
        RevisionValidationResult $result,
        int $order,
        int $refOrder,
        RevisionActionType $expectedActionType,
    ): bool {
        if ($refOrder >= $order) {
            $this->addActionError($result, $order, 'REFERENCE_NOT_PREVIOUS', '引用的 ref_order 必須指向前序操作');

            return false;
        }

        $referencedAction = $this->actionByOrder[$refOrder] ?? null;
        if ($referencedAction === null) {
            $this->addActionError($result, $order, 'REFERENCE_NOT_FOUND', '引用的 ref_order 不存在');

            return false;
        }

        if ($referencedAction->action !== $expectedActionType) {
            $this->addActionError(
                $result,
                $order,
                'REFERENCE_ACTION_TYPE_MISMATCH',
                '引用的操作類型不符合要求',
                ['expected_action' => $expectedActionType->value],
            );

            return false;
        }

        if ($this->actionHasError($refOrder)) {
            $this->addActionError(
                $result,
                $order,
                'DEPENDENCY_INVALID',
                '依賴的前序操作無效，請先修正前序操作',
                ['dependency_order' => $refOrder],
            );

            return false;
        }

        return true;
    }

    private function valueMatchesType(string $value, PropertyType $propertyType): bool
    {
        return match ($propertyType) {
            PropertyType::Integer => preg_match('/^-?\\d+$/', $value) === 1,
            PropertyType::Float, PropertyType::Numeric => preg_match('/^-?(?:\\d+|\\d*\\.\\d+)$/', $value) === 1,
            PropertyType::Boolean => in_array(strtolower($value), ['true', 'false'], true),
            PropertyType::String => true,
        };
    }

    private function actionHasError(int $order): bool
    {
        return $this->actionHasError[$order] ?? false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function addActionError(
        RevisionValidationResult $result,
        int $order,
        string $code,
        string $message,
        array $meta = [],
    ): void {
        $result->addActionError($order, $code, $message, $meta);
        $this->actionHasError[$order] = true;
    }

    private function isProvided(mixed $value): bool
    {
        return ! is_null($value) && $value !== '';
    }

    private function vertexExists(string $key): bool
    {
        return ($this->vertexStates[$key]['exists'] ?? false) === true;
    }

    private function edgeExists(string $key): bool
    {
        return ($this->edgeStates[$key]['exists'] ?? false) === true;
    }

    private function vertexTypeLabel(string $key): ?string
    {
        return $this->vertexStates[$key]['type_label'] ?? null;
    }

    private function edgeTypeLabel(string $key): ?string
    {
        return $this->edgeStates[$key]['type_label'] ?? null;
    }

    private function hasVertexProperty(string $key, string $propertyName): bool
    {
        return ($this->vertexStates[$key]['properties'][$propertyName] ?? false) === true;
    }

    private function hasEdgeProperty(string $key, string $propertyName): bool
    {
        return ($this->edgeStates[$key]['properties'][$propertyName] ?? false) === true;
    }

    private function setVertexPropertyExists(string $key, string $propertyName, bool $exists): void
    {
        if (! isset($this->vertexStates[$key])) {
            return;
        }

        $this->vertexStates[$key]['properties'][$propertyName] = $exists;
    }

    private function setEdgePropertyExists(string $key, string $propertyName, bool $exists): void
    {
        if (! isset($this->edgeStates[$key])) {
            return;
        }

        $this->edgeStates[$key]['properties'][$propertyName] = $exists;
    }

    private function markVertexDeleted(string $key): void
    {
        if (isset($this->vertexStates[$key])) {
            $this->vertexStates[$key]['exists'] = false;
        }
    }

    private function markEdgeDeleted(string $key): void
    {
        if (isset($this->edgeStates[$key])) {
            $this->edgeStates[$key]['exists'] = false;
        }
    }

    /**
     * @return list<string>
     */
    private function findRemainingConnectedEdgeKeys(string $vertexKey): array
    {
        if (str_starts_with($vertexKey, 'age:')) {
            $vertexId = (int) substr($vertexKey, 4);
            foreach ($this->loadConnectedEdgeIds($vertexId) as $edgeId) {
                $this->loadAgeEdgeState($edgeId);
            }
        }

        $remaining = [];
        foreach ($this->edgeStates as $edgeKey => $state) {
            if (! $state['exists']) {
                continue;
            }

            if ($state['start_key'] === $vertexKey || $state['end_key'] === $vertexKey) {
                $remaining[] = $edgeKey;
            }
        }

        return $remaining;
    }

    private function bootSchemaMaps(): void
    {
        $this->vertexTypeByLabel = VertexType::query()
            ->with('properties')
            ->get()
            ->keyBy('age_label_name')
            ->all();

        $this->edgeTypeByLabel = EdgeType::query()
            ->with(['startVertex', 'endVertex', 'properties'])
            ->get()
            ->keyBy('age_label_name')
            ->all();
    }

    private function loadAgeVertexState(int $vertexId): void
    {
        if (isset($this->ageVertexCache[$vertexId])) {
            $cached = $this->ageVertexCache[$vertexId];
            $this->vertexStates[$this->ageVertexKey($vertexId)] = [
                'exists' => $cached['exists'],
                'type_label' => $cached['type_label'],
                'properties' => $cached['properties'],
            ];

            return;
        }

        $record = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexId) {
            return $builder
                ->matchNode('v')
                ->where('id(v)', '=', $vertexId)
                ->return('v');
        })->first();

        if ($record === null) {
            $this->ageVertexCache[$vertexId] = [
                'exists' => false,
                'type_label' => '',
                'properties' => [],
            ];
            $this->vertexStates[$this->ageVertexKey($vertexId)] = [
                'exists' => false,
                'type_label' => '',
                'properties' => [],
            ];

            return;
        }

        $vertex = $record->v;
        $properties = [];
        foreach ($this->normalizeProperties($vertex->properties ?? []) as $name => $value) {
            $properties[$name] = ! is_null($value);
        }

        $cached = [
            'exists' => true,
            'type_label' => (string) $vertex->label,
            'properties' => $properties,
        ];

        $this->ageVertexCache[$vertexId] = $cached;
        $this->vertexStates[$this->ageVertexKey($vertexId)] = [
            'exists' => true,
            'type_label' => $cached['type_label'],
            'properties' => $cached['properties'],
        ];
    }

    private function loadAgeEdgeState(int $edgeId): void
    {
        if (isset($this->ageEdgeCache[$edgeId])) {
            $cached = $this->ageEdgeCache[$edgeId];
            $this->edgeStates[$this->ageEdgeKey($edgeId)] = [
                'exists' => $cached['exists'],
                'type_label' => $cached['type_label'],
                'start_key' => $this->ageVertexKey($cached['start']),
                'end_key' => $this->ageVertexKey($cached['end']),
                'properties' => $cached['properties'],
            ];

            if ($cached['exists']) {
                $this->loadAgeVertexState($cached['start']);
                $this->loadAgeVertexState($cached['end']);
            }

            return;
        }

        $record = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($edgeId) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('t')
                ->where('id(e)', '=', $edgeId)
                ->return(['e', 's', 't']);
        })->first();

        if ($record === null) {
            $this->ageEdgeCache[$edgeId] = [
                'exists' => false,
                'type_label' => '',
                'start' => 0,
                'end' => 0,
                'properties' => [],
            ];
            $this->edgeStates[$this->ageEdgeKey($edgeId)] = [
                'exists' => false,
                'type_label' => '',
                'start_key' => '',
                'end_key' => '',
                'properties' => [],
            ];

            return;
        }

        $edge = $record->e;
        $start = $record->s;
        $end = $record->t;

        $properties = [];
        foreach ($this->normalizeProperties($edge->properties ?? []) as $name => $value) {
            $properties[$name] = ! is_null($value);
        }

        $startId = (int) $start->id;
        $endId = (int) $end->id;

        $cached = [
            'exists' => true,
            'type_label' => (string) $edge->label,
            'start' => $startId,
            'end' => $endId,
            'properties' => $properties,
        ];

        $this->ageEdgeCache[$edgeId] = $cached;
        $this->loadAgeVertexState($startId);
        $this->loadAgeVertexState($endId);

        $this->edgeStates[$this->ageEdgeKey($edgeId)] = [
            'exists' => true,
            'type_label' => $cached['type_label'],
            'start_key' => $this->ageVertexKey($startId),
            'end_key' => $this->ageVertexKey($endId),
            'properties' => $properties,
        ];
    }

    /**
     * @return list<int>
     */
    private function loadConnectedEdgeIds(int $vertexId): array
    {
        $rows = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexId) {
            return $builder
                ->matchNode('v')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('m')
                ->where('id(v)', '=', $vertexId)
                ->return('e');
        })->get();

        $edgeIds = [];
        foreach ($rows as $row) {
            $edgeIds[] = (int) $row->e->id;
        }

        return array_values(array_unique($edgeIds));
    }

    /**
     * @param  array<string, mixed>|object  $properties
     * @return array<string, mixed>
     */
    private function normalizeProperties(array|object $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }

        return (array) $properties;
    }

    private function refVertexKey(int $order): string
    {
        return 'ref:'.$order;
    }

    private function ageVertexKey(int $id): string
    {
        return 'age:'.$id;
    }

    private function refEdgeKey(int $order): string
    {
        return 'ref:'.$order;
    }

    private function ageEdgeKey(int $id): string
    {
        return 'age:'.$id;
    }

    private function graphConnection(): \Illuminate\Database\PostgresConnection
    {
        /** @var \Illuminate\Database\PostgresConnection $connection */
        $connection = DB::connection((string) config('cohistograph.app.graph.connection-name'));

        return $connection;
    }
}
