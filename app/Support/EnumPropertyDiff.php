<?php

namespace App\Support;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\RevisionAction;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Services\Revision\AgeGraphStateManager;
use Illuminate\Support\Collection;

/**
 * Builds ENUM revision display diffs (before / added / removed) for action cards.
 *
 * @phpstan-type EnumDiff array{
 *     is_enum: bool,
 *     is_create_ref_target: bool,
 *     before_labels: string,
 *     added_labels: string,
 *     removed_labels: string,
 *     value_labels: string
 * }
 */
class EnumPropertyDiff
{
    public function __construct(
        private AgeGraphStateManager $graphManager,
        private PropertyValueCaster $propertyValueCaster,
    ) {}

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     * @param  Collection<int, VertexType>|iterable<int, VertexType>  $vertexTypes
     * @param  Collection<int, EdgeType>|iterable<int, EdgeType>  $edgeTypes
     * @return EnumDiff|null
     */
    public function forAction(
        RevisionAction $action,
        Collection|iterable $revisionActions,
        iterable $vertexTypes,
        iterable $edgeTypes,
    ): ?array {
        if (! $this->isPropertyAction($action)) {
            return null;
        }

        $property = $this->resolveProperty($action, $revisionActions, $vertexTypes, $edgeTypes);
        if ($property === null || $property->age_property_type !== PropertyType::Enum) {
            return null;
        }

        $options = EnumOptions::normalize(is_array($property->enum_options) ? $property->enum_options : null);
        $after = $this->normalizeList($action->value);
        $isDelete = in_array($action->action, [
            RevisionActionType::DeleteVertexProperty,
            RevisionActionType::DeleteEdgeProperty,
        ], true);

        if ($isDelete) {
            $after = [];
        }

        $isCreateRefTarget = ! is_null($action->target_ref_order);
        $before = $isCreateRefTarget
            ? []
            : $this->loadBeforeFromAge($action);

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        return [
            'is_enum' => true,
            'is_create_ref_target' => $isCreateRefTarget,
            'before_labels' => $before === [] ? '（無）' : EnumOptions::formatLabels($before, $options),
            'added_labels' => $added === [] ? '' : EnumOptions::formatLabels($added, $options),
            'removed_labels' => $removed === [] ? '' : EnumOptions::formatLabels($removed, $options),
            'value_labels' => $after === []
                ? '—'
                : EnumOptions::formatLabels($after, $options),
        ];
    }

    public function formatActionValue(RevisionAction $action, VertexProperty|EdgeProperty|null $property): string
    {
        if ($property === null || $property->age_property_type !== PropertyType::Enum) {
            if (is_array($action->value)) {
                return implode(', ', array_map('strval', $action->value));
            }

            if (is_bool($action->value)) {
                return $action->value ? 'true' : 'false';
            }

            return $action->value === null ? '—' : (string) $action->value;
        }

        $options = EnumOptions::normalize(is_array($property->enum_options) ? $property->enum_options : null);

        return EnumOptions::formatLabels($this->normalizeList($action->value), $options);
    }

    private function isPropertyAction(RevisionAction $action): bool
    {
        return in_array($action->action, [
            RevisionActionType::CreateVertexProperty,
            RevisionActionType::UpdateVertexProperty,
            RevisionActionType::DeleteVertexProperty,
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty,
            RevisionActionType::DeleteEdgeProperty,
        ], true);
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     * @param  iterable<int, VertexType>  $vertexTypes
     * @param  iterable<int, EdgeType>  $edgeTypes
     */
    private function resolveProperty(
        RevisionAction $action,
        Collection|iterable $revisionActions,
        iterable $vertexTypes,
        iterable $edgeTypes,
    ): VertexProperty|EdgeProperty|null {
        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            return null;
        }

        $isEdge = in_array($action->action, [
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty,
            RevisionActionType::DeleteEdgeProperty,
        ], true);

        if ($isEdge) {
            $typeLabel = $this->resolveEdgeTypeLabel($action, $revisionActions);
            foreach ($edgeTypes as $edgeType) {
                if ($edgeType->age_label_name !== $typeLabel) {
                    continue;
                }

                return $edgeType->properties->firstWhere('age_property_name', $propertyName);
            }

            return null;
        }

        $typeLabel = $this->resolveVertexTypeLabel($action, $revisionActions);
        foreach ($vertexTypes as $vertexType) {
            if ($vertexType->age_label_name !== $typeLabel) {
                continue;
            }

            return $vertexType->properties->firstWhere('age_property_name', $propertyName);
        }

        return null;
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     */
    private function resolveVertexTypeLabel(RevisionAction $action, Collection|iterable $revisionActions): ?string
    {
        if (! is_null($action->target_ref_order)) {
            foreach ($revisionActions as $candidate) {
                if ((int) $candidate->order === (int) $action->target_ref_order) {
                    return $candidate->vertex_type_label;
                }
            }

            return null;
        }

        if (is_null($action->target_age_id)) {
            return null;
        }

        try {
            return $this->graphManager->loadAgeVertexState((int) $action->target_age_id)['type_label'] ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     */
    private function resolveEdgeTypeLabel(RevisionAction $action, Collection|iterable $revisionActions): ?string
    {
        if (! is_null($action->target_ref_order)) {
            foreach ($revisionActions as $candidate) {
                if ((int) $candidate->order === (int) $action->target_ref_order) {
                    return $candidate->edge_type_label;
                }
            }

            return null;
        }

        if (is_null($action->target_age_id)) {
            return null;
        }

        try {
            return $this->graphManager->loadAgeEdgeState((int) $action->target_age_id)['type_label'] ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function loadBeforeFromAge(RevisionAction $action): array
    {
        if (is_null($action->target_age_id) || ! is_string($action->age_property_name)) {
            return [];
        }

        try {
            $isEdge = in_array($action->action, [
                RevisionActionType::CreateEdgeProperty,
                RevisionActionType::UpdateEdgeProperty,
                RevisionActionType::DeleteEdgeProperty,
            ], true);

            $state = $isEdge
                ? $this->graphManager->loadAgeEdgeState((int) $action->target_age_id)
                : $this->graphManager->loadAgeVertexState((int) $action->target_age_id);

            $raw = $state['property_values'][$action->age_property_name] ?? null;

            return $this->normalizeList($this->propertyValueCaster->fromStorage($raw, PropertyType::Enum));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
