<?php

namespace App\Services\Revision;

use App\Enums\RevisionActionType;
use App\Models\RevisionAction;

/**
 * 解析和驗證 Revision 操作的引用和依賴關係
 *
 * 負責：
 * - 解析頂點目標（Age ID 或 ref_order）
 * - 解析邊目標與起訖頂點
 * - 驗證引用依賴
 * - 維護操作狀態追踪（operationByOrder, actionHasError）
 * - 管理運行時狀態（vertexStates, edgeStates）
 */
class RevisionActionResolver
{
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

    private AgeGraphStateManager $graphManager;

    /**
     * @param  array<int, RevisionAction>  $actionByOrder
     * @param  array<int, bool>  $actionHasError
     */
    public function __construct(
        AgeGraphStateManager $graphManager,
        array $actionByOrder,
        array $actionHasError,
    ) {
        $this->graphManager = $graphManager;
        $this->actionByOrder = $actionByOrder;
        $this->actionHasError = $actionHasError;
    }

    /**
     * @return array{exists:bool,type_label:string,properties:array<string,bool>}|null
     */
    public function getVertexState(string $key): ?array
    {
        return $this->vertexStates[$key] ?? null;
    }

    /**
     * @return array{exists:bool,type_label:string,start_key:string,end_key:string,properties:array<string,bool>}|null
     */
    public function getEdgeState(string $key): ?array
    {
        return $this->edgeStates[$key] ?? null;
    }

    /**
     * @param  array{exists:bool,type_label:string,properties:array<string,bool>}  $state
     */
    public function setVertexState(string $key, array $state): void
    {
        $this->vertexStates[$key] = $state;
    }

    /**
     * @param  array{exists:bool,type_label:string,start_key:string,end_key:string,properties:array<string,bool>}  $state
     */
    public function setEdgeState(string $key, array $state): void
    {
        $this->edgeStates[$key] = $state;
    }

    public function vertexExists(string $key): bool
    {
        return ($this->vertexStates[$key]['exists'] ?? false) === true;
    }

    public function edgeExists(string $key): bool
    {
        return ($this->edgeStates[$key]['exists'] ?? false) === true;
    }

    public function vertexTypeLabel(string $key): ?string
    {
        return $this->vertexStates[$key]['type_label'] ?? null;
    }

    public function edgeTypeLabel(string $key): ?string
    {
        return $this->edgeStates[$key]['type_label'] ?? null;
    }

    public function markActionErrorForDependency(int $order): void
    {
        $this->actionHasError[$order] = true;
    }

    public function hasVertexProperty(string $key, string $propertyName): bool
    {
        return ($this->vertexStates[$key]['properties'][$propertyName] ?? false) === true;
    }

    public function hasEdgeProperty(string $key, string $propertyName): bool
    {
        return ($this->edgeStates[$key]['properties'][$propertyName] ?? false) === true;
    }

    public function setVertexPropertyExists(string $key, string $propertyName, bool $exists): void
    {
        if (! isset($this->vertexStates[$key])) {
            return;
        }

        $this->vertexStates[$key]['properties'][$propertyName] = $exists;
    }

    public function setEdgePropertyExists(string $key, string $propertyName, bool $exists): void
    {
        if (! isset($this->edgeStates[$key])) {
            return;
        }

        $this->edgeStates[$key]['properties'][$propertyName] = $exists;
    }

    public function markVertexDeleted(string $key): void
    {
        if (isset($this->vertexStates[$key])) {
            $this->vertexStates[$key]['exists'] = false;
        }
    }

    public function markEdgeDeleted(string $key): void
    {
        if (isset($this->edgeStates[$key])) {
            $this->edgeStates[$key]['exists'] = false;
        }
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    public function resolveVertexTarget(
        RevisionAction $action,
        RevisionValidationResult $result,
        string $context,
    ): ?array {
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
            $result->addActionError($order, 'MISSING_TARGET', "{$context} 需要指定 target");

            return null;
        }

        $ageId = (int) $action->target_age_id;

        return [
            'key' => $this->ageVertexKey($ageId),
            'is_ref' => false,
        ];
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    public function resolveEdgeTarget(
        RevisionAction $action,
        RevisionValidationResult $result,
        string $context,
    ): ?array {
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
            $result->addActionError($order, 'MISSING_TARGET', "{$context} 需要指定 target");

            return null;
        }

        $ageId = (int) $action->target_age_id;

        return [
            'key' => $this->ageEdgeKey($ageId),
            'is_ref' => false,
        ];
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    public function resolveStartVertex(RevisionAction $action, RevisionValidationResult $result): ?array
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
            $result->addActionError($order, 'MISSING_START_VERTEX', 'create_edge 需要指定起始 Vertex');

            return null;
        }

        $ageId = (int) $action->start_vertex_age_id;

        return ['key' => $this->ageVertexKey($ageId), 'is_ref' => false];
    }

    /**
     * @return array{key:string,is_ref:bool}|null
     */
    public function resolveEndVertex(RevisionAction $action, RevisionValidationResult $result): ?array
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
            $result->addActionError($order, 'MISSING_END_VERTEX', 'create_edge 需要指定終止 Vertex');

            return null;
        }

        $ageId = (int) $action->end_vertex_age_id;

        return ['key' => $this->ageVertexKey($ageId), 'is_ref' => false];
    }

    public function validateReferenceDependency(
        RevisionValidationResult $result,
        int $order,
        int $refOrder,
        RevisionActionType $expectedActionType,
    ): bool {
        if ($refOrder >= $order) {
            $result->addActionError($order, 'REFERENCE_NOT_PREVIOUS', '引用的 ref_order 必須指向前序操作');

            return false;
        }

        $referencedAction = $this->actionByOrder[$refOrder] ?? null;
        if ($referencedAction === null) {
            $result->addActionError($order, 'REFERENCE_NOT_FOUND', '引用的 ref_order 不存在');

            return false;
        }

        if ($referencedAction->action !== $expectedActionType) {
            $result->addActionError(
                $order,
                'REFERENCE_ACTION_TYPE_MISMATCH',
                '引用的操作類型不符合要求',
                ['expected_action' => $expectedActionType->value],
            );

            return false;
        }

        if ($this->actionHasError[$refOrder] ?? false) {
            $result->addActionError(
                $order,
                'DEPENDENCY_INVALID',
                '依賴的前序操作無效，請先修正前序操作',
                ['dependency_order' => $refOrder],
            );

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function findRemainingConnectedEdgeKeys(string $vertexKey): array
    {
        // 如果是 Age DB 的頂點，先從圖數據庫查詢連接的邊
        if (str_starts_with($vertexKey, 'age:')) {
            $vertexId = (int) substr($vertexKey, 4);
            foreach ($this->graphManager->loadConnectedEdgeIds($vertexId) as $edgeId) {
                $this->graphManager->loadAgeEdgeState($edgeId);
            }

            // 同步新加載的 Age Edge 狀態到 resolver
            foreach ($this->graphManager->getAllLoadedEdgeStates() as $edgeId => $state) {
                $edgeKey = 'age:'.$edgeId;
                if (! isset($this->edgeStates[$edgeKey])) {
                    if ($state['exists']) {
                        // 確保起訖頂點也被同步
                        $this->graphManager->loadAgeVertexState($state['start']);
                        $this->graphManager->loadAgeVertexState($state['end']);
                    }

                    $this->edgeStates[$edgeKey] = [
                        'exists' => $state['exists'],
                        'type_label' => $state['type_label'],
                        'start_key' => 'age:'.$state['start'],
                        'end_key' => 'age:'.$state['end'],
                        'properties' => $state['properties'],
                    ];
                }
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
}
