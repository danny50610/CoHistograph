<?php

namespace App\Services\Revision;

use App\Enums\RevisionActionType;
use App\Models\Revision;
use App\Models\RevisionAction;

/**
 * 協調 Revision 驗證流程的服務
 *
 * 職責：
 * - 協調驗證流程
 * - 委派給 AgeGraphStateManager、RevisionActionResolver 和 RevisionActionValidator
 *
 * 不再直接處理業務邏輯、圖數據查詢或引用解析
 */
class RevisionValidationService
{
    private AgeGraphStateManager $graphManager;

    public function __construct(AgeGraphStateManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    public function validate(Revision $revision): RevisionValidationResult
    {
        $this->graphManager->bootSchemaMaps();

        $result = new RevisionValidationResult;
        $actions = $revision->actions()
            ->orderBy('order')
            ->get();

        if ($actions->isEmpty()) {
            $result->addGeneralError('至少需要一筆操作才能提交審核');

            return $result;
        }

        // 建立 action 映射和追踪錯誤狀態
        $actionByOrder = [];
        $actionHasError = [];
        foreach ($actions as $expectedOrder => $action) {
            $actionByOrder[(int) $action->order] = $action;

            if ((int) $action->order !== $expectedOrder) {
                $result->addActionError(
                    (int) $action->order,
                    'ORDER_NOT_CONTIGUOUS',
                    '操作順序不連續，請重新整理後儲存草稿',
                    ['expected_order' => $expectedOrder],
                );
                $actionHasError[(int) $action->order] = true;
            }
        }

        // 載入所有 Age 相關的狀態
        $this->preloadAllAgeStates($actions);

        // 創建協作對象
        $resolver = new RevisionActionResolver($this->graphManager, $actionByOrder, $actionHasError);

        // 將已加載的 Age 狀態轉移到 resolver
        $this->syncAgeStatesToResolver($resolver);

        $validator = new RevisionActionValidator($this->graphManager, $resolver);

        // 驗證每個操作
        foreach ($actions as $action) {
            $order = (int) $action->order;
            $actionHasError[$order] = $actionHasError[$order] ?? false;

            // 在驗證前同步之前所有操作的錯誤狀態到 resolver
            for ($prevOrder = 0; $prevOrder < $order; $prevOrder++) {
                if ($actionHasError[$prevOrder] ?? false) {
                    $resolver->markActionErrorForDependency($prevOrder);
                }
            }

            $validator->validateSingleAction($action, $result);

            // 同步本次驗證的錯誤狀態
            if ($validator->hasActionError($order)) {
                $actionHasError[$order] = true;
            }
        }

        // 最後同步所有錯誤狀態到 resolver，以便任何遗留的狀態也被更新
        $allValidatorErrors = $validator->getAllActionErrors();
        foreach ($allValidatorErrors as $order => $hasError) {
            if ($hasError) {
                $resolver->markActionErrorForDependency($order);
            }
        }

        return $result;
    }

    /**
     * 預載所有需要從 Age 圖數據庫查詢的狀態
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, RevisionAction>  $actions
     */
    private function preloadAllAgeStates(\Illuminate\Database\Eloquent\Collection $actions): void
    {
        $edgeTargetTypes = [
            RevisionActionType::DeleteEdge,
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty,
            RevisionActionType::DeleteEdgeProperty,
        ];

        foreach ($actions as $action) {
            if (! is_null($action->target_age_id)) {
                if (in_array($action->action, $edgeTargetTypes, true)) {
                    $this->graphManager->loadAgeEdgeState((int) $action->target_age_id);
                } else {
                    $this->graphManager->loadAgeVertexState((int) $action->target_age_id);
                }
            }

            if (! is_null($action->start_vertex_age_id)) {
                $this->graphManager->loadAgeVertexState((int) $action->start_vertex_age_id);
            }

            if (! is_null($action->end_vertex_age_id)) {
                $this->graphManager->loadAgeVertexState((int) $action->end_vertex_age_id);
            }
        }
    }

    /**
     * 將已加載的 Age 狀態同步到 resolver
     */
    private function syncAgeStatesToResolver(RevisionActionResolver $resolver): void
    {
        // 同步 Vertex 狀態
        foreach ($this->graphManager->getAllLoadedVertexStates() as $vertexId => $state) {
            $resolver->setVertexState('age:'.$vertexId, $state);
        }

        // 同步 Edge 狀態及其相關的頂點
        foreach ($this->graphManager->getAllLoadedEdgeStates() as $edgeId => $state) {
            if ($state['exists']) {
                // 確保起訖頂點也被加載
                $this->graphManager->loadAgeVertexState($state['start']);
                $this->graphManager->loadAgeVertexState($state['end']);
            }

            $resolver->setEdgeState('age:'.$edgeId, [
                'exists' => $state['exists'],
                'type_label' => $state['type_label'],
                'start_key' => 'age:'.$state['start'],
                'end_key' => 'age:'.$state['end'],
                'properties' => $state['properties'],
            ]);
        }

        // 重新同步 Vertex 狀態以確保任何新加載的都包含在內
        foreach ($this->graphManager->getAllLoadedVertexStates() as $vertexId => $state) {
            $resolver->setVertexState('age:'.$vertexId, $state);
        }
    }
}
