<?php

namespace App\Services\Revision;

use App\Enums\RevisionActionType;
use App\Models\RevisionAction;
use App\Support\VertexDisplayNameResolver;
use Illuminate\Support\Collection;

/**
 * Soft-check for likely duplicate vertices/edges.
 * Warnings never affect RevisionValidationResult::isValid().
 */
class RevisionDuplicateWarningChecker
{
    public function __construct(
        private AgeGraphStateManager $graphManager,
        private VertexDisplayNameResolver $displayNameResolver,
    ) {}

    /**
     * @param  Collection<int, RevisionAction>  $actions
     * @param  array<int, bool>  $actionHasError
     */
    public function check(
        Collection $actions,
        RevisionActionResolver $resolver,
        RevisionValidationResult $result,
        array $actionHasError,
    ): void {
        $this->warnDuplicateEdges($actions, $resolver, $result, $actionHasError);
        $this->warnDuplicateVertices($actions, $resolver, $result, $actionHasError);
    }

    /**
     * @param  Collection<int, RevisionAction>  $actions
     * @param  array<int, bool>  $actionHasError
     */
    private function warnDuplicateEdges(
        Collection $actions,
        RevisionActionResolver $resolver,
        RevisionValidationResult $result,
        array $actionHasError,
    ): void {
        foreach ($actions as $action) {
            if ($action->action !== RevisionActionType::CreateEdge) {
                continue;
            }

            $order = (int) $action->order;
            if ($actionHasError[$order] ?? false) {
                continue;
            }

            $selfKey = 'ref:'.$order;
            $state = $resolver->getEdgeState($selfKey);
            if ($state === null || ($state['exists'] ?? false) !== true) {
                continue;
            }

            $edgeLabel = (string) ($state['type_label'] ?? '');
            $startKey = (string) ($state['start_key'] ?? '');
            $endKey = (string) ($state['end_key'] ?? '');
            if ($edgeLabel === '' || $startKey === '' || $endKey === '') {
                continue;
            }

            $conflicts = [];

            foreach ($resolver->getAllEdgeStates() as $edgeKey => $edgeState) {
                if ($edgeKey === $selfKey) {
                    continue;
                }

                if (($edgeState['exists'] ?? false) !== true) {
                    continue;
                }

                if (
                    ($edgeState['type_label'] ?? null) === $edgeLabel
                    && ($edgeState['start_key'] ?? null) === $startKey
                    && ($edgeState['end_key'] ?? null) === $endKey
                ) {
                    $conflicts[] = $this->describeEdgeConflict($edgeKey);
                }
            }

            $startAgeId = $this->ageIdFromKey($startKey);
            $endAgeId = $this->ageIdFromKey($endKey);
            if ($startAgeId !== null && $endAgeId !== null) {
                foreach ($this->graphManager->findEdgeIdsByEndpoints($edgeLabel, $startAgeId, $endAgeId) as $edgeId) {
                    $ageKey = 'age:'.$edgeId;
                    if (($resolver->getEdgeState($ageKey)['exists'] ?? true) === false) {
                        continue;
                    }

                    $conflicts[] = [
                        'existing_edge_age_id' => $edgeId,
                    ];
                }
            }

            $conflicts = $this->uniqueConflicts($conflicts);
            if ($conflicts === []) {
                continue;
            }

            $primary = $conflicts[0];
            $result->addActionWarning(
                $order,
                'DUPLICATE_EDGE',
                $this->duplicateEdgeMessage($edgeLabel, $startKey, $endKey, $primary),
                [
                    'edge_type_label' => $edgeLabel,
                    'start_key' => $startKey,
                    'end_key' => $endKey,
                    'conflicts' => $conflicts,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, RevisionAction>  $actions
     * @param  array<int, bool>  $actionHasError
     */
    private function warnDuplicateVertices(
        Collection $actions,
        RevisionActionResolver $resolver,
        RevisionValidationResult $result,
        array $actionHasError,
    ): void {
        foreach ($actions as $action) {
            if ($action->action !== RevisionActionType::CreateVertex) {
                continue;
            }

            $order = (int) $action->order;
            if ($actionHasError[$order] ?? false) {
                continue;
            }

            $selfKey = 'ref:'.$order;
            $state = $resolver->getVertexState($selfKey);
            if ($state === null || ($state['exists'] ?? false) !== true) {
                continue;
            }

            $typeLabel = (string) ($state['type_label'] ?? '');
            $vertexType = $this->graphManager->getVertexTypeByLabel()[$typeLabel] ?? null;
            if ($vertexType === null) {
                continue;
            }

            $displayName = trim($this->displayNameResolver->resolve(
                $vertexType->show_property_name,
                $state['property_values'] ?? [],
                $vertexType->properties,
            ));

            if ($displayName === '') {
                continue;
            }

            $conflicts = [];

            foreach ($resolver->getAllVertexStates() as $vertexKey => $vertexState) {
                if ($vertexKey === $selfKey) {
                    continue;
                }

                if (($vertexState['exists'] ?? false) !== true) {
                    continue;
                }

                if (($vertexState['type_label'] ?? null) !== $typeLabel) {
                    continue;
                }

                $otherName = trim($this->displayNameResolver->resolve(
                    $vertexType->show_property_name,
                    $vertexState['property_values'] ?? [],
                    $vertexType->properties,
                ));

                if ($otherName !== '' && $otherName === $displayName) {
                    $conflicts[] = $this->describeVertexConflict($vertexKey);
                }
            }

            foreach ($this->graphManager->loadVerticesByType($typeLabel) as $vertex) {
                $ageKey = 'age:'.$vertex['age_id'];
                if (($resolver->getVertexState($ageKey)['exists'] ?? true) === false) {
                    continue;
                }

                $otherName = trim($this->displayNameResolver->resolve(
                    $vertexType->show_property_name,
                    $vertex['properties'],
                    $vertexType->properties,
                ));

                if ($otherName !== '' && $otherName === $displayName) {
                    $conflicts[] = [
                        'existing_vertex_age_id' => $vertex['age_id'],
                    ];
                }
            }

            $conflicts = $this->uniqueConflicts($conflicts);
            if ($conflicts === []) {
                continue;
            }

            $primary = $conflicts[0];
            $result->addActionWarning(
                $order,
                'DUPLICATE_VERTEX',
                $this->duplicateVertexMessage($typeLabel, $displayName, $primary),
                [
                    'vertex_type_label' => $typeLabel,
                    'display_name' => $displayName,
                    'conflicts' => $conflicts,
                ],
            );
        }
    }

    /**
     * @return array{conflict_ref_order?:int,existing_edge_age_id?:int,existing_vertex_age_id?:int}
     */
    private function describeEdgeConflict(string $edgeKey): array
    {
        if (str_starts_with($edgeKey, 'ref:')) {
            return ['conflict_ref_order' => (int) substr($edgeKey, 4)];
        }

        return ['existing_edge_age_id' => (int) substr($edgeKey, 4)];
    }

    /**
     * @return array{conflict_ref_order?:int,existing_vertex_age_id?:int}
     */
    private function describeVertexConflict(string $vertexKey): array
    {
        if (str_starts_with($vertexKey, 'ref:')) {
            return ['conflict_ref_order' => (int) substr($vertexKey, 4)];
        }

        return ['existing_vertex_age_id' => (int) substr($vertexKey, 4)];
    }

    private function ageIdFromKey(string $key): ?int
    {
        if (! str_starts_with($key, 'age:')) {
            return null;
        }

        return (int) substr($key, 4);
    }

    /**
     * @param  list<array{conflict_ref_order?:int,existing_edge_age_id?:int,existing_vertex_age_id?:int}>  $conflicts
     * @return list<array{conflict_ref_order?:int,existing_edge_age_id?:int,existing_vertex_age_id?:int}>
     */
    private function uniqueConflicts(array $conflicts): array
    {
        $unique = [];
        $seen = [];

        foreach ($conflicts as $conflict) {
            $key = json_encode($conflict, JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $conflict;
        }

        return $unique;
    }

    /**
     * @param  array{conflict_ref_order?:int,existing_edge_age_id?:int,existing_vertex_age_id?:int}  $conflict
     */
    private function duplicateEdgeMessage(string $edgeLabel, string $startKey, string $endKey, array $conflict): string
    {
        $target = $this->conflictLabel($conflict);

        return "可能重複的邊：已存在相同「{$this->vertexKeyLabel($startKey)} - {$edgeLabel} - {$this->vertexKeyLabel($endKey)}」（{$target}）";
    }

    /**
     * @param  array{conflict_ref_order?:int,existing_edge_age_id?:int,existing_vertex_age_id?:int}  $conflict
     */
    private function duplicateVertexMessage(string $typeLabel, string $displayName, array $conflict): string
    {
        $target = $this->conflictLabel($conflict);

        return "可能重複的節點：同類型「{$typeLabel}」已有顯示名稱「{$displayName}」的節點（{$target}）";
    }

    /**
     * @param  array{conflict_ref_order?:int,existing_edge_age_id?:int,existing_vertex_age_id?:int}  $conflict
     */
    private function conflictLabel(array $conflict): string
    {
        if (isset($conflict['conflict_ref_order'])) {
            return '操作 #'.(((int) $conflict['conflict_ref_order']) + 1);
        }

        if (isset($conflict['existing_edge_age_id'])) {
            return 'Edge ID:'.$conflict['existing_edge_age_id'];
        }

        if (isset($conflict['existing_vertex_age_id'])) {
            return 'Vertex ID:'.$conflict['existing_vertex_age_id'];
        }

        return '既有項目';
    }

    private function vertexKeyLabel(string $key): string
    {
        if (str_starts_with($key, 'ref:')) {
            return '操作 #'.(((int) substr($key, 4)) + 1);
        }

        if (str_starts_with($key, 'age:')) {
            return 'ID:'.substr($key, 4);
        }

        return $key;
    }
}
