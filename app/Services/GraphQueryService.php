<?php

namespace App\Services;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GraphQueryService
{
    /**
     * @return array{
     *     vertex_type_label: string,
     *     query: string|null,
     *     total: int,
     *     vertices: list<array{age_id: int, label: string, properties: array<string, mixed>}>
     * }
     */
    public function searchVertices(
        string $vertexTypeLabel,
        ?string $query = null,
        ?string $property = null,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $vertexType = VertexType::query()
            ->where('age_label_name', $vertexTypeLabel)
            ->with('properties')
            ->first();

        if ($vertexType === null) {
            throw new InvalidArgumentException("找不到 Vertex 類型: {$vertexTypeLabel}");
        }

        $query = $this->normalizeOptionalQuery($query);
        $searchableProperties = $this->resolveStringPropertyNames($vertexType->properties, $property);

        if ($query !== null && $property !== null && $searchableProperties === []) {
            throw new InvalidArgumentException("屬性 {$property} 不存在或不是 STRING 類型");
        }

        $allVertices = $this->fetchVertices($vertexTypeLabel, $query, $searchableProperties);
        $total = $allVertices->count();
        $vertices = $allVertices
            ->slice($offset, $limit)
            ->values()
            ->map(fn (object $vertex): array => [
                'age_id' => (int) $vertex->id,
                'label' => (string) $vertex->label,
                'properties' => $this->normalizeAgeProperties($vertex->properties ?? []),
            ])
            ->all();

        return [
            'vertex_type_label' => $vertexTypeLabel,
            'query' => $query,
            'total' => $total,
            'vertices' => $vertices,
        ];
    }

    /**
     * @return array{age_id: int, label: string, properties: array<string, mixed>}|null
     */
    public function getVertex(int $ageId): ?array
    {
        $record = $this->graphConnection()->apacheAgeCypher($this->graphName(), function (Builder $builder) use ($ageId) {
            return $builder->matchNode('v')
                ->where('id(v)', '=', $ageId)
                ->return('v');
        })->first();

        if ($record === null) {
            return null;
        }

        $vertex = $record->v;

        return [
            'age_id' => (int) $vertex->id,
            'label' => (string) $vertex->label,
            'properties' => $this->normalizeAgeProperties($vertex->properties ?? []),
        ];
    }

    /**
     * @return array{
     *     query: string|null,
     *     total: int,
     *     edges: list<array{
     *         age_id: int,
     *         label: string,
     *         properties: array<string, mixed>,
     *         start_vertex: array{age_id: int, label: string, properties: array<string, mixed>},
     *         end_vertex: array{age_id: int, label: string, properties: array<string, mixed>}
     *     }>
     * }
     */
    public function searchEdges(
        ?string $edgeTypeLabel = null,
        ?int $startVertexAgeId = null,
        ?int $endVertexAgeId = null,
        ?string $query = null,
        ?string $property = null,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $query = $this->normalizeOptionalQuery($query);

        if ($edgeTypeLabel === null && $startVertexAgeId === null && $endVertexAgeId === null && $query === null) {
            throw new InvalidArgumentException('至少需提供 edge_type_label、start_vertex_age_id、end_vertex_age_id 或 query 其中一項');
        }

        $edgeTypes = EdgeType::query()
            ->when($edgeTypeLabel !== null, fn ($q) => $q->where('age_label_name', $edgeTypeLabel))
            ->with('properties')
            ->get();

        if ($edgeTypeLabel !== null && $edgeTypes->isEmpty()) {
            throw new InvalidArgumentException("找不到 Edge 類型: {$edgeTypeLabel}");
        }

        if ($property !== null) {
            $hasMatchingStringProperty = $edgeTypes->contains(
                fn (EdgeType $edgeType): bool => $this->resolveStringPropertyNames($edgeType->properties, $property) !== []
            );

            if (! $hasMatchingStringProperty) {
                throw new InvalidArgumentException("屬性 {$property} 不存在或不是 STRING 類型");
            }
        }

        $edges = $this->fetchEdges($edgeTypeLabel, $startVertexAgeId, $endVertexAgeId)
            ->filter(function (array $edge) use ($query, $property, $edgeTypes): bool {
                if ($query === null) {
                    return true;
                }

                /** @var EdgeType|null $edgeType */
                $edgeType = $edgeTypes->firstWhere('age_label_name', $edge['label']);
                $searchable = $edgeType === null
                    ? []
                    : $this->resolveStringPropertyNames($edgeType->properties, $property);

                if ($searchable === []) {
                    return false;
                }

                $needle = mb_strtolower($query);

                foreach ($searchable as $propertyName) {
                    $value = $edge['properties'][$propertyName] ?? null;
                    if (is_string($value) && str_contains(mb_strtolower($value), $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $total = $edges->count();

        return [
            'query' => $query,
            'total' => $total,
            'edges' => $edges->slice($offset, $limit)->values()->all(),
        ];
    }

    /**
     * @return array{
     *     age_id: int,
     *     label: string,
     *     properties: array<string, mixed>,
     *     start_vertex: array{age_id: int, label: string, properties: array<string, mixed>},
     *     end_vertex: array{age_id: int, label: string, properties: array<string, mixed>}
     * }|null
     */
    public function getEdge(int $ageId): ?array
    {
        $record = $this->graphConnection()->apacheAgeCypher($this->graphName(), function (Builder $builder) use ($ageId) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('t')
                ->where('id(e)', '=', $ageId)
                ->return(['e', 's', 't']);
        })->first();

        if ($record === null) {
            return null;
        }

        return $this->formatEdgeRecord($record->e, $record->s, $record->t);
    }

    /**
     * @return array{
     *     age_id: int,
     *     direction: string,
     *     neighbors: list<array{
     *         direction: string,
     *         edge: array{age_id: int, label: string, properties: array<string, mixed>},
     *         vertex: array{age_id: int, label: string, properties: array<string, mixed>}
     *     }>
     * }
     */
    public function listVertexNeighbors(int $ageId, string $direction = 'both'): array
    {
        $vertex = $this->getVertex($ageId);
        if ($vertex === null) {
            throw new InvalidArgumentException("找不到頂點: {$ageId}");
        }

        $directions = match ($direction) {
            'outgoing' => [Direction::RIGHT],
            'incoming' => [Direction::LEFT],
            'both' => [Direction::RIGHT, Direction::LEFT],
            default => throw new InvalidArgumentException('direction 必須是 outgoing、incoming 或 both'),
        };

        $neighbors = [];

        foreach ($directions as $ageDirection) {
            $records = $this->graphConnection()->apacheAgeCypher($this->graphName(), function (Builder $builder) use ($ageId, $vertex, $ageDirection) {
                return $builder->matchNode('v', $vertex['label'])
                    ->withMatchEdge($ageDirection, 'e')
                    ->withMatchNode('m')
                    ->where('id(v)', '=', $ageId)
                    ->return(['e', 'm']);
            })->get();

            $directionLabel = $ageDirection === Direction::RIGHT ? 'outgoing' : 'incoming';

            foreach ($records as $record) {
                $edge = $record->e;
                $neighbor = $record->m;

                $neighbors[] = [
                    'direction' => $directionLabel,
                    'edge' => [
                        'age_id' => (int) $edge->id,
                        'label' => (string) $edge->label,
                        'properties' => $this->normalizeAgeProperties($edge->properties ?? []),
                    ],
                    'vertex' => [
                        'age_id' => (int) $neighbor->id,
                        'label' => (string) $neighbor->label,
                        'properties' => $this->normalizeAgeProperties($neighbor->properties ?? []),
                    ],
                ];
            }
        }

        return [
            'age_id' => $ageId,
            'direction' => $direction,
            'neighbors' => $neighbors,
        ];
    }

    /**
     * @param  Collection<int, VertexProperty>|Collection<int, EdgeProperty>  $properties
     * @return list<string>
     */
    private function resolveStringPropertyNames(Collection $properties, ?string $property): array
    {
        return $properties
            ->filter(fn ($item): bool => $item->age_property_type === PropertyType::String)
            ->when($property !== null, fn (Collection $items) => $items->where('age_property_name', $property))
            ->pluck('age_property_name')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $searchableProperties
     * @return Collection<int, object>
     */
    private function fetchVertices(string $vertexTypeLabel, ?string $query, array $searchableProperties): Collection
    {
        $records = $this->graphConnection()->apacheAgeCypher($this->graphName(), function (Builder $builder) use ($vertexTypeLabel) {
            return $builder->matchNode('v', $vertexTypeLabel)
                ->return('v');
        })->get();

        $vertices = $records->map(fn (object $item) => $item->v);

        if ($query === null) {
            return $vertices->values();
        }

        if ($searchableProperties === []) {
            return collect();
        }

        $needle = mb_strtolower($query);

        return $vertices
            ->filter(function (object $vertex) use ($searchableProperties, $needle): bool {
                $properties = $this->normalizeAgeProperties($vertex->properties ?? []);

                foreach ($searchableProperties as $propertyName) {
                    $value = $properties[$propertyName] ?? null;
                    if (is_string($value) && str_contains(mb_strtolower($value), $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     age_id: int,
     *     label: string,
     *     properties: array<string, mixed>,
     *     start_vertex: array{age_id: int, label: string, properties: array<string, mixed>},
     *     end_vertex: array{age_id: int, label: string, properties: array<string, mixed>}
     * }>
     */
    private function fetchEdges(?string $edgeTypeLabel, ?int $startVertexAgeId, ?int $endVertexAgeId): Collection
    {
        $records = $this->graphConnection()->apacheAgeCypher($this->graphName(), function (Builder $builder) use ($edgeTypeLabel) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::RIGHT, 'e', $edgeTypeLabel)
                ->withMatchNode('t')
                ->return(['e', 's', 't']);
        })->get();

        return $records
            ->map(fn (object $record): array => $this->formatEdgeRecord($record->e, $record->s, $record->t))
            ->filter(function (array $edge) use ($startVertexAgeId, $endVertexAgeId): bool {
                if ($startVertexAgeId !== null && $edge['start_vertex']['age_id'] !== $startVertexAgeId) {
                    return false;
                }

                if ($endVertexAgeId !== null && $edge['end_vertex']['age_id'] !== $endVertexAgeId) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array{
     *     age_id: int,
     *     label: string,
     *     properties: array<string, mixed>,
     *     start_vertex: array{age_id: int, label: string, properties: array<string, mixed>},
     *     end_vertex: array{age_id: int, label: string, properties: array<string, mixed>}
     * }
     */
    private function formatEdgeRecord(object $edge, object $start, object $end): array
    {
        return [
            'age_id' => (int) $edge->id,
            'label' => (string) $edge->label,
            'properties' => $this->normalizeAgeProperties($edge->properties ?? []),
            'start_vertex' => [
                'age_id' => (int) $start->id,
                'label' => (string) $start->label,
                'properties' => $this->normalizeAgeProperties($start->properties ?? []),
            ],
            'end_vertex' => [
                'age_id' => (int) $end->id,
                'label' => (string) $end->label,
                'properties' => $this->normalizeAgeProperties($end->properties ?? []),
            ],
        ];
    }

    private function normalizeOptionalQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $query = trim($query);

        return $query === '' ? null : $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAgeProperties(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }

        if (is_object($properties)) {
            return (array) $properties;
        }

        return [];
    }

    private function graphConnection()
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'));
    }

    private function graphName(): string
    {
        return (string) config('cohistograph.app.graph.name');
    }
}
