<?php

namespace App\Services\Revision;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\RevisionAction;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\EnumOptions;
use App\Support\LocalizedPropertyLabelResolver;
use App\Support\VertexDisplayNameResolver;
use Illuminate\Support\Collection;

/**
 * Builds a read-only subgraph of the vertices and edges touched by a revision.
 *
 * @phpstan-type GraphProperty array{name: string, age_property_name: string, value: string, status: 'created'|'updated'|'deleted'}
 * @phpstan-type GraphVertex array{id: string, label: string, type_label: string, type_name: string, status: 'created'|'deleted'|'updated'|'unchanged', age_id: string|null, ref_order: int|null, url: string|null, properties: list<GraphProperty>}
 * @phpstan-type GraphEdge array{id: string, label: string, type_label: string, status: 'created'|'deleted'|'updated'|'unchanged', start_id: string, end_id: string, age_id: string|null, ref_order: int|null, properties: list<GraphProperty>}
 */
class RevisionGraphPreviewBuilder
{
    /**
     * @var array<string, array{type_label: string, status: string, age_id: string|null, ref_order: int|null, exists_in_age: bool, property_values: array<string, mixed>, property_changes: array<string, GraphProperty>}>
     */
    private array $vertices = [];

    /**
     * @var array<string, array{type_label: string, status: string, start_id: string, end_id: string, age_id: string|null, ref_order: int|null, property_values: array<string, mixed>, property_changes: array<string, GraphProperty>}>
     */
    private array $edges = [];

    public function __construct(
        private AgeGraphStateManager $graphManager,
        private VertexDisplayNameResolver $displayNameResolver,
        private LocalizedPropertyLabelResolver $propertyLabelResolver,
    ) {}

    /**
     * @param  Collection<int, RevisionAction>|iterable<int, RevisionAction>  $actions
     * @return array{vertices: list<GraphVertex>, edges: list<GraphEdge>}
     */
    public function build(iterable $actions): array
    {
        $this->vertices = [];
        $this->edges = [];
        $this->graphManager->bootSchemaMaps();

        $ordered = collect($actions)->sortBy(fn (RevisionAction $action) => (int) $action->order)->values();

        foreach ($ordered as $action) {
            $this->applyAction($action);
        }

        return [
            'vertices' => array_map(fn (string $id): array => $this->serializeVertex($id), array_keys($this->vertices)),
            'edges' => array_map(fn (string $id): array => $this->serializeEdge($id), array_keys($this->edges)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return array{vertices: list<GraphVertex>, edges: list<GraphEdge>}
     */
    public function buildFromPayloads(array $payloads): array
    {
        $actions = collect($payloads)
            ->values()
            ->map(function (array $payload, int $index): RevisionAction {
                return new RevisionAction([
                    'order' => $index,
                    'action' => $payload['action'],
                    'target_age_id' => $payload['target_age_id'] ?? null,
                    'target_ref_order' => $payload['target_ref_order'] ?? null,
                    'vertex_type_label' => $payload['vertex_type_label'] ?? null,
                    'edge_type_label' => $payload['edge_type_label'] ?? null,
                    'start_vertex_age_id' => $payload['start_vertex_age_id'] ?? null,
                    'start_vertex_ref_order' => $payload['start_vertex_ref_order'] ?? null,
                    'end_vertex_age_id' => $payload['end_vertex_age_id'] ?? null,
                    'end_vertex_ref_order' => $payload['end_vertex_ref_order'] ?? null,
                    'age_property_name' => $payload['age_property_name'] ?? null,
                    'value' => $payload['value'] ?? null,
                ]);
            });

        return $this->build($actions);
    }

    private function applyAction(RevisionAction $action): void
    {
        match ($action->action) {
            RevisionActionType::CreateVertex => $this->applyCreateVertex($action),
            RevisionActionType::DeleteVertex => $this->applyDeleteVertex($action),
            RevisionActionType::CreateEdge => $this->applyCreateEdge($action),
            RevisionActionType::DeleteEdge => $this->applyDeleteEdge($action),
            RevisionActionType::CreateVertexProperty => $this->applyVertexProperty($action, 'created'),
            RevisionActionType::UpdateVertexProperty => $this->applyVertexProperty($action, 'updated'),
            RevisionActionType::DeleteVertexProperty => $this->applyVertexProperty($action, 'deleted'),
            RevisionActionType::CreateEdgeProperty => $this->applyEdgeProperty($action, 'created'),
            RevisionActionType::UpdateEdgeProperty => $this->applyEdgeProperty($action, 'updated'),
            RevisionActionType::DeleteEdgeProperty => $this->applyEdgeProperty($action, 'deleted'),
        };
    }

    private function applyCreateVertex(RevisionAction $action): void
    {
        $order = (int) $action->order;
        $key = $this->refKey($order);
        $typeLabel = is_string($action->vertex_type_label) ? $action->vertex_type_label : '';

        $this->vertices[$key] = [
            'type_label' => $typeLabel,
            'status' => 'created',
            'age_id' => null,
            'ref_order' => $order,
            'exists_in_age' => false,
            'property_values' => [],
            'property_changes' => [],
        ];
    }

    private function applyDeleteVertex(RevisionAction $action): void
    {
        $key = $this->resolveVertexKey($action->target_ref_order, $action->target_age_id);
        if ($key === null) {
            return;
        }

        $this->vertices[$key]['status'] = 'deleted';
    }

    private function applyCreateEdge(RevisionAction $action): void
    {
        $startId = $this->resolveVertexKey($action->start_vertex_ref_order, $action->start_vertex_age_id);
        $endId = $this->resolveVertexKey($action->end_vertex_ref_order, $action->end_vertex_age_id);
        if ($startId === null || $endId === null) {
            return;
        }

        $order = (int) $action->order;
        $key = $this->refKey($order);
        $typeLabel = is_string($action->edge_type_label) ? $action->edge_type_label : '';

        $this->edges[$key] = [
            'type_label' => $typeLabel,
            'status' => 'created',
            'start_id' => $startId,
            'end_id' => $endId,
            'age_id' => null,
            'ref_order' => $order,
            'property_values' => [],
            'property_changes' => [],
        ];
    }

    private function applyDeleteEdge(RevisionAction $action): void
    {
        if (! is_null($action->target_ref_order)) {
            $key = $this->refKey((int) $action->target_ref_order);
            if (isset($this->edges[$key])) {
                $this->edges[$key]['status'] = 'deleted';
            }

            return;
        }

        if ($action->target_age_id === null || $action->target_age_id === '') {
            return;
        }

        $key = $this->ensureAgeEdge((string) $action->target_age_id);
        if ($key !== null) {
            $this->edges[$key]['status'] = 'deleted';
        }
    }

    /**
     * @param  'created'|'updated'|'deleted'  $changeStatus
     */
    private function applyVertexProperty(RevisionAction $action, string $changeStatus): void
    {
        $key = $this->resolveVertexKey($action->target_ref_order, $action->target_age_id);
        if ($key === null) {
            return;
        }

        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            return;
        }

        $this->vertices[$key]['status'] = $this->combineStatus($this->vertices[$key]['status'], 'updated');

        $vertexType = $this->vertexType($this->vertices[$key]['type_label']);
        $properties = $vertexType instanceof VertexType ? $vertexType->properties : collect();
        $property = $properties->firstWhere('age_property_name', $propertyName);

        if ($changeStatus === 'deleted') {
            unset($this->vertices[$key]['property_values'][$propertyName]);
            $value = '—';
        } else {
            $this->vertices[$key]['property_values'][$propertyName] = $action->value;
            $value = $this->formatPropertyValue($action->value, $property);
        }

        $this->vertices[$key]['property_changes'][$propertyName] = [
            'name' => is_string($property?->name) ? $property->name : $propertyName,
            'age_property_name' => $this->propertyLabelResolver->formatAgePropertyName(
                $propertyName,
                $properties,
            ),
            'value' => $value,
            'status' => $changeStatus,
        ];
    }

    /**
     * @param  'created'|'updated'|'deleted'  $changeStatus
     */
    private function applyEdgeProperty(RevisionAction $action, string $changeStatus): void
    {
        $key = $this->resolveEdgeKey($action->target_ref_order, $action->target_age_id);
        if ($key === null) {
            return;
        }

        $propertyName = $action->age_property_name;
        if (! is_string($propertyName) || $propertyName === '') {
            return;
        }

        $this->edges[$key]['status'] = $this->combineStatus($this->edges[$key]['status'], 'updated');

        $edgeType = $this->edgeType($this->edges[$key]['type_label']);
        $properties = $edgeType instanceof EdgeType ? $edgeType->properties : collect();
        $property = $properties->firstWhere('age_property_name', $propertyName);

        if ($changeStatus === 'deleted') {
            unset($this->edges[$key]['property_values'][$propertyName]);
            $value = '—';
        } else {
            $this->edges[$key]['property_values'][$propertyName] = $action->value;
            $value = $this->formatPropertyValue($action->value, $property);
        }

        $this->edges[$key]['property_changes'][$propertyName] = [
            'name' => is_string($property?->name) ? $property->name : $propertyName,
            'age_property_name' => $this->propertyLabelResolver->formatAgePropertyName(
                $propertyName,
                $properties,
            ),
            'value' => $value,
            'status' => $changeStatus,
        ];
    }

    private function resolveVertexKey(?int $refOrder, ?string $ageId): ?string
    {
        if ($refOrder !== null) {
            $key = $this->refKey($refOrder);
            if (! isset($this->vertices[$key])) {
                $this->vertices[$key] = [
                    'type_label' => '',
                    'status' => 'created',
                    'age_id' => null,
                    'ref_order' => $refOrder,
                    'exists_in_age' => false,
                    'property_values' => [],
                    'property_changes' => [],
                ];
            }

            return $key;
        }

        if ($ageId === null || $ageId === '') {
            return null;
        }

        return $this->ensureAgeVertex($ageId);
    }

    private function resolveEdgeKey(?int $refOrder, ?string $ageId): ?string
    {
        if ($refOrder !== null) {
            $key = $this->refKey($refOrder);

            return isset($this->edges[$key]) ? $key : null;
        }

        if ($ageId === null || $ageId === '') {
            return null;
        }

        return $this->ensureAgeEdge($ageId);
    }

    private function ensureAgeVertex(string $ageId): string
    {
        $key = $this->ageKey($ageId);
        if (isset($this->vertices[$key])) {
            return $key;
        }

        $state = $this->graphManager->loadAgeVertexState((int) $ageId);

        $this->vertices[$key] = [
            'type_label' => $state['type_label'],
            'status' => 'unchanged',
            'age_id' => $ageId,
            'ref_order' => null,
            'exists_in_age' => $state['exists'],
            'property_values' => $state['exists'] ? $state['property_values'] : [],
            'property_changes' => [],
        ];

        return $key;
    }

    private function ensureAgeEdge(string $ageId): ?string
    {
        $key = $this->ageKey($ageId);
        if (isset($this->edges[$key])) {
            return $key;
        }

        $state = $this->graphManager->loadAgeEdgeState((int) $ageId);
        if (! $state['exists'] || $state['start'] === 0 || $state['end'] === 0) {
            return null;
        }

        $startId = $this->ensureAgeVertex((string) $state['start']);
        $endId = $this->ensureAgeVertex((string) $state['end']);

        $this->edges[$key] = [
            'type_label' => $state['type_label'],
            'status' => 'unchanged',
            'start_id' => $startId,
            'end_id' => $endId,
            'age_id' => $ageId,
            'ref_order' => null,
            'property_values' => $state['property_values'],
            'property_changes' => [],
        ];

        return $key;
    }

    /**
     * @return GraphVertex
     */
    private function serializeVertex(string $id): array
    {
        $vertex = $this->vertices[$id];
        $vertexType = $this->vertexType($vertex['type_label']);
        $typeName = $vertexType instanceof VertexType
            ? $vertexType->name
            : ($vertex['type_label'] !== '' ? $vertex['type_label'] : 'Vertex');
        $label = $this->vertexLabel($vertex, $vertexType, $typeName);

        return [
            'id' => $id,
            'label' => $label,
            'type_label' => $vertex['type_label'],
            'type_name' => $typeName,
            'status' => $this->normalizeStatus($vertex['status']),
            'age_id' => $vertex['age_id'],
            'ref_order' => $vertex['ref_order'],
            'url' => $vertex['exists_in_age'] && $vertex['age_id'] !== null
                ? route('graph.vertex.show', (int) $vertex['age_id'])
                : null,
            'properties' => array_values($vertex['property_changes']),
        ];
    }

    /**
     * @return GraphEdge
     */
    private function serializeEdge(string $id): array
    {
        $edge = $this->edges[$id];
        $edgeType = $this->edgeType($edge['type_label']);
        $edgeLabel = $edgeType instanceof EdgeType
            ? $edgeType->name
            : ($edge['type_label'] !== '' ? $edge['type_label'] : 'Edge');

        return [
            'id' => $id,
            'label' => $edgeLabel,
            'type_label' => $edge['type_label'],
            'status' => $this->normalizeStatus($edge['status']),
            'start_id' => $edge['start_id'],
            'end_id' => $edge['end_id'],
            'age_id' => $edge['age_id'],
            'ref_order' => $edge['ref_order'],
            'properties' => array_values($edge['property_changes']),
        ];
    }

    /**
     * @param  array{type_label: string, status: string, age_id: string|null, ref_order: int|null, exists_in_age: bool, property_values: array<string, mixed>, property_changes: array<string, GraphProperty>}  $vertex
     */
    private function vertexLabel(array $vertex, ?VertexType $vertexType, string $typeName): string
    {
        $displayName = $this->displayNameResolver->resolve(
            $vertexType?->show_property_name,
            $vertex['property_values'],
            $vertexType instanceof VertexType ? $vertexType->properties : collect(),
        );

        if ($displayName !== '') {
            return $displayName;
        }

        if ($vertex['age_id'] !== null) {
            return 'ID:'.$vertex['age_id'];
        }

        if ($vertex['ref_order'] !== null) {
            return $typeName.'（新建 #'.($vertex['ref_order'] + 1).'）';
        }

        return $typeName;
    }

    private function formatPropertyValue(mixed $value, VertexProperty|EdgeProperty|null $property): string
    {
        if ($value === null) {
            return '—';
        }

        if ($property?->age_property_type === PropertyType::Enum) {
            $options = EnumOptions::normalize(is_array($property->enum_options) ? $property->enum_options : null);
            $values = is_array($value)
                ? array_map(static fn (mixed $item): string => (string) $item, $value)
                : [(string) $value];

            return EnumOptions::formatLabels($values, $options);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode('、', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        return (string) $value;
    }

    /**
     * @param  'created'|'deleted'|'updated'|'unchanged'|string  $current
     * @param  'created'|'deleted'|'updated'|'unchanged'|string  $incoming
     * @return 'created'|'deleted'|'updated'|'unchanged'
     */
    private function combineStatus(string $current, string $incoming): string
    {
        if ($incoming === 'deleted' || $current === 'deleted') {
            return 'deleted';
        }

        if ($current === 'created') {
            return 'created';
        }

        if ($incoming === 'created') {
            return 'created';
        }

        if ($incoming === 'updated' || $current === 'updated') {
            return 'updated';
        }

        return 'unchanged';
    }

    /**
     * @return 'created'|'deleted'|'updated'|'unchanged'
     */
    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'created', 'deleted', 'updated', 'unchanged' => $status,
            default => 'unchanged',
        };
    }

    private function vertexType(string $label): ?VertexType
    {
        if ($label === '') {
            return null;
        }

        return $this->graphManager->getVertexTypeByLabel()[$label] ?? null;
    }

    private function edgeType(string $label): ?EdgeType
    {
        if ($label === '') {
            return null;
        }

        return $this->graphManager->getEdgeTypeByLabel()[$label] ?? null;
    }

    private function refKey(int $order): string
    {
        return 'ref:'.$order;
    }

    private function ageKey(string $ageId): string
    {
        return 'age:'.$ageId;
    }
}
