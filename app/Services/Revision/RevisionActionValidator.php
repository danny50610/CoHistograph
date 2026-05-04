<?php

namespace App\Services\Revision;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\RevisionAction;

/**
 * 驗證單一 Revision 操作的邏輯
 *
 * 負責：
 * - Vertex 和 Edge 的創建/刪除驗證
 * - 屬性的創建/更新/刪除驗證
 * - 欄位有效性驗證
 * - 參數型別檢查
 */
class RevisionActionValidator
{
    private AgeGraphStateManager $graphManager;

    private RevisionActionResolver $resolver;

    private array $actionHasError = [];

    public function __construct(
        AgeGraphStateManager $graphManager,
        RevisionActionResolver $resolver,
    ) {
        $this->graphManager = $graphManager;
        $this->resolver = $resolver;
    }

    public function markActionError(int $order): void
    {
        $this->actionHasError[$order] = true;
    }

    public function hasActionError(int $order): bool
    {
        return $this->actionHasError[$order] ?? false;
    }

    /**
     * @return array<int, bool>
     */
    public function getAllActionErrors(): array
    {
        return $this->actionHasError;
    }

    public function validateSingleAction(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $actionType = $action->action;
        if (! $actionType instanceof RevisionActionType) {
            $this->addActionError($result, $order, 'INVALID_ACTION_TYPE', '不支援的操作類型');

            return;
        }

        $this->validateAllowedFields($action, $result);

        if ($this->hasActionError($order)) {
            return;
        }

        match ($actionType) {
            RevisionActionType::CreateVertex => $this->validateCreateVertex($action, $result),
            RevisionActionType::DeleteVertex => $this->validateDeleteVertex($action, $result),
            RevisionActionType::CreateEdge => $this->validateCreateEdge($action, $result),
            RevisionActionType::DeleteEdge => $this->validateDeleteEdge($action, $result),
            RevisionActionType::CreateVertexProperty => $this->validateVertexPropertyAction($action, $result, true, false),
            RevisionActionType::UpdateVertexProperty => $this->validateVertexPropertyAction($action, $result, false, false),
            RevisionActionType::DeleteVertexProperty => $this->validateVertexPropertyAction($action, $result, false, true),
            RevisionActionType::CreateEdgeProperty => $this->validateEdgePropertyAction($action, $result, true, false),
            RevisionActionType::UpdateEdgeProperty => $this->validateEdgePropertyAction($action, $result, false, false),
            RevisionActionType::DeleteEdgeProperty => $this->validateEdgePropertyAction($action, $result, false, true),
        };
    }

    private function validateCreateVertex(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $label = $action->vertex_type_label;

        if (! is_string($label) || $label === '') {
            $this->addActionError($result, $order, 'MISSING_VERTEX_TYPE', '新增 Vertex 需要指定 vertex_type_label');

            return;
        }

        if (! isset($this->graphManager->getVertexTypeByLabel()[$label])) {
            $this->addActionError($result, $order, 'VERTEX_TYPE_NOT_FOUND', "找不到 Vertex 類型: {$label}");

            return;
        }

        $this->resolver->setVertexState($this->refVertexKey($order), [
            'exists' => true,
            'type_label' => $label,
            'properties' => [],
        ]);
    }

    private function validateDeleteVertex(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $target = $this->resolver->resolveVertexTarget($action, $result, 'delete_vertex');
        if ($target === null) {
            return;
        }

        if (! $this->resolver->vertexExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Vertex 不存在');

            return;
        }

        $remainingEdges = $this->resolver->findRemainingConnectedEdgeKeys($target['key']);
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

        $this->resolver->markVertexDeleted($target['key']);
    }

    private function validateCreateEdge(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $edgeLabel = $action->edge_type_label;
        if (! is_string($edgeLabel) || $edgeLabel === '') {
            $this->addActionError($result, $order, 'MISSING_EDGE_TYPE', '新增 Edge 需要指定 edge_type_label');

            return;
        }

        $edgeType = $this->graphManager->getEdgeTypeByLabel()[$edgeLabel] ?? null;
        if ($edgeType === null) {
            $this->addActionError($result, $order, 'EDGE_TYPE_NOT_FOUND', "找不到 Edge 類型: {$edgeLabel}");

            return;
        }

        $start = $this->resolver->resolveStartVertex($action, $result);
        $end = $this->resolver->resolveEndVertex($action, $result);
        if ($start === null || $end === null) {
            return;
        }

        if (! $this->resolver->vertexExists($start['key'])) {
            $this->addActionError($result, $order, 'START_VERTEX_NOT_FOUND', '起始 Vertex 不存在');
        }

        if (! $this->resolver->vertexExists($end['key'])) {
            $this->addActionError($result, $order, 'END_VERTEX_NOT_FOUND', '終止 Vertex 不存在');
        }

        if ($this->hasActionError($order)) {
            return;
        }

        $expectedStart = $edgeType->startVertex?->age_label_name;
        $expectedEnd = $edgeType->endVertex?->age_label_name;
        $actualStart = $this->resolver->vertexTypeLabel($start['key']);
        $actualEnd = $this->resolver->vertexTypeLabel($end['key']);

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

        $this->resolver->setEdgeState($this->refEdgeKey($order), [
            'exists' => true,
            'type_label' => $edgeLabel,
            'start_key' => $start['key'],
            'end_key' => $end['key'],
            'properties' => [],
        ]);
    }

    private function validateDeleteEdge(RevisionAction $action, RevisionValidationResult $result): void
    {
        $order = (int) $action->order;
        $target = $this->resolver->resolveEdgeTarget($action, $result, 'delete_edge');
        if ($target === null) {
            return;
        }

        if (! $this->resolver->edgeExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Edge 不存在');

            return;
        }

        $this->resolver->markEdgeDeleted($target['key']);
    }

    private function validateVertexPropertyAction(
        RevisionAction $action,
        RevisionValidationResult $result,
        bool $isCreate,
        bool $isDelete,
    ): void {
        $order = (int) $action->order;
        $target = $this->resolver->resolveVertexTarget($action, $result, $action->action->value);
        if ($target === null) {
            return;
        }

        if (! $this->resolver->vertexExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Vertex 不存在');

            return;
        }

        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            $this->addActionError($result, $order, 'MISSING_PROPERTY_NAME', '需要指定 age_property_name');

            return;
        }

        $vertexTypeLabel = $this->resolver->vertexTypeLabel($target['key']);
        $vertexType = $vertexTypeLabel ? ($this->graphManager->getVertexTypeByLabel()[$vertexTypeLabel] ?? null) : null;
        $property = $vertexType?->properties->firstWhere('age_property_name', $propertyName);

        if ($property === null) {
            $this->addActionError($result, $order, 'PROPERTY_NOT_FOUND', '指定的 Vertex 屬性不存在');

            return;
        }

        $hasProperty = $this->resolver->hasVertexProperty($target['key'], $propertyName);
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

            $this->resolver->setVertexPropertyExists($target['key'], $propertyName, false);

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

        $this->resolver->setVertexPropertyExists($target['key'], $propertyName, true);
    }

    private function validateEdgePropertyAction(
        RevisionAction $action,
        RevisionValidationResult $result,
        bool $isCreate,
        bool $isDelete,
    ): void {
        $order = (int) $action->order;
        $target = $this->resolver->resolveEdgeTarget($action, $result, $action->action->value);
        if ($target === null) {
            return;
        }

        if (! $this->resolver->edgeExists($target['key'])) {
            $this->addActionError($result, $order, 'TARGET_NOT_FOUND', '目標 Edge 不存在');

            return;
        }

        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            $this->addActionError($result, $order, 'MISSING_PROPERTY_NAME', '需要指定 age_property_name');

            return;
        }

        $edgeTypeLabel = $this->resolver->edgeTypeLabel($target['key']);
        $edgeType = $edgeTypeLabel ? ($this->graphManager->getEdgeTypeByLabel()[$edgeTypeLabel] ?? null) : null;
        $property = $edgeType?->properties->firstWhere('age_property_name', $propertyName);

        if ($property === null) {
            $this->addActionError($result, $order, 'PROPERTY_NOT_FOUND', '指定的 Edge 屬性不存在');

            return;
        }

        $hasProperty = $this->resolver->hasEdgeProperty($target['key'], $propertyName);
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

            $this->resolver->setEdgePropertyExists($target['key'], $propertyName, false);

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

        $this->resolver->setEdgePropertyExists($target['key'], $propertyName, true);
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

    private function valueMatchesType(string $value, PropertyType $propertyType): bool
    {
        return match ($propertyType) {
            PropertyType::Integer => preg_match('/^-?\\d+$/', $value) === 1,
            PropertyType::Float, PropertyType::Numeric => preg_match('/^-?(?:\\d+|\\d*\\.\\d+)$/', $value) === 1,
            PropertyType::Boolean => in_array(strtolower($value), ['true', 'false'], true),
            PropertyType::String => true,
        };
    }

    private function isProvided(mixed $value): bool
    {
        return ! is_null($value) && $value !== '';
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
        $this->markActionError($order);
    }

    private function refVertexKey(int $order): string
    {
        return 'ref:'.$order;
    }

    private function refEdgeKey(int $order): string
    {
        return 'ref:'.$order;
    }
}
