<?php

namespace App\Support;

use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\RevisionAction;
use App\Models\VertexProperty;
use Illuminate\Support\Collection;

class LocalizedPropertyLabelResolver
{
    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     * @param  Collection<int, \App\Models\VertexType>  $vertexTypes
     * @param  Collection<int, \App\Models\EdgeType>  $edgeTypes
     */
    public function formatForAction(
        RevisionAction $action,
        Collection $revisionActions,
        Collection $vertexTypes,
        Collection $edgeTypes,
    ): string {
        $agePropertyName = $action->age_property_name;

        if ($agePropertyName === null || $agePropertyName === '') {
            return '—';
        }

        $properties = match ($action->action) {
            RevisionActionType::CreateVertexProperty,
            RevisionActionType::UpdateVertexProperty,
            RevisionActionType::DeleteVertexProperty => $this->propertiesForVertexPropertyAction(
                $action,
                $revisionActions,
                $vertexTypes,
            ),
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty,
            RevisionActionType::DeleteEdgeProperty => $this->propertiesForEdgePropertyAction(
                $action,
                $revisionActions,
                $edgeTypes,
            ),
            default => collect(),
        };

        return $this->formatAgePropertyName($agePropertyName, $properties);
    }

    /**
     * @param  iterable<VertexProperty|EdgeProperty>  $properties
     */
    public function formatAgePropertyName(string $agePropertyName, iterable $properties): string
    {
        $property = collect($properties)->firstWhere('age_property_name', $agePropertyName);

        if ($property?->locale === null) {
            return $agePropertyName;
        }

        $localeLabel = config('cohistograph.app.graph.locales')[$property->locale] ?? $property->locale;

        return "{$agePropertyName}（{$localeLabel}）";
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     * @param  Collection<int, \App\Models\VertexType>  $vertexTypes
     * @return Collection<int, VertexProperty>
     */
    private function propertiesForVertexPropertyAction(
        RevisionAction $action,
        Collection $revisionActions,
        Collection $vertexTypes,
    ): Collection {
        $vertexTypeLabel = $this->resolveVertexTypeLabel($action, $revisionActions);

        if ($vertexTypeLabel !== null) {
            $vertexType = $vertexTypes->firstWhere('age_label_name', $vertexTypeLabel);

            if ($vertexType === null) {
                return collect();
            }

            return $vertexType->properties;
        }

        return $vertexTypes->flatMap(fn ($vertexType) => $vertexType->properties);
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     * @param  Collection<int, \App\Models\EdgeType>  $edgeTypes
     * @return Collection<int, EdgeProperty>
     */
    private function propertiesForEdgePropertyAction(
        RevisionAction $action,
        Collection $revisionActions,
        Collection $edgeTypes,
    ): Collection {
        $edgeTypeLabel = $this->resolveEdgeTypeLabel($action, $revisionActions);

        if ($edgeTypeLabel !== null) {
            $edgeType = $edgeTypes->firstWhere('age_label_name', $edgeTypeLabel);

            if ($edgeType === null) {
                return collect();
            }

            return $edgeType->properties;
        }

        return $edgeTypes->flatMap(fn ($edgeType) => $edgeType->properties);
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     */
    private function resolveVertexTypeLabel(RevisionAction $action, Collection $revisionActions): ?string
    {
        if ($action->target_ref_order === null) {
            return null;
        }

        $referencedAction = $revisionActions->firstWhere('order', $action->target_ref_order);

        return $referencedAction?->vertex_type_label;
    }

    /**
     * @param  Collection<int, RevisionAction>  $revisionActions
     */
    private function resolveEdgeTypeLabel(RevisionAction $action, Collection $revisionActions): ?string
    {
        if ($action->target_ref_order === null) {
            return null;
        }

        $referencedAction = $revisionActions->firstWhere('order', $action->target_ref_order);

        return $referencedAction?->edge_type_label;
    }
}
