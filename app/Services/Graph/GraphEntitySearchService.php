<?php

namespace App\Services\Graph;

use App\Models\EdgeType;
use App\Models\VertexType;
use App\Support\VertexDisplayNameResolver;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GraphEntitySearchService
{
    public function __construct(
        private VertexDisplayNameResolver $displayNameResolver,
    ) {}

    /**
     * @param  list<string>|null  $typeLabels
     * @return list<array{id:int,display_name:string,type_label:string,type_name:string}>
     */
    public function searchVertices(string $query = '', ?array $typeLabels = null, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $query = trim($query);

        $vertexTypes = $this->resolveVertexTypes($typeLabels);

        if ($vertexTypes->isEmpty()) {
            return [];
        }

        $results = [];
        $needle = Str::lower($query);
        $exactId = ctype_digit($query) ? (int) $query : null;

        foreach ($vertexTypes as $vertexType) {
            $vertices = $this->loadVerticesByType($vertexType);

            foreach ($vertices as $item) {
                $vertex = $item->v;
                $id = (int) $vertex->id;
                $displayName = $this->displayNameResolver->resolve(
                    $vertexType->show_property_name,
                    $this->normalizeProperties($vertex->properties ?? []),
                    $vertexType->properties,
                );

                if (! $this->matchesVertexQuery($needle, $exactId, $id, $displayName, $vertexType)) {
                    continue;
                }

                $results[] = [
                    'id' => $id,
                    'display_name' => $displayName !== '' ? $displayName : "(ID: {$id})",
                    'type_label' => $vertexType->age_label_name,
                    'type_name' => $vertexType->name,
                ];

                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * @return array{id:int,display_name:string,type_label:string,type_name:string}|null
     */
    public function findVertex(int $id): ?array
    {
        $record = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id) {
            return $builder->matchNode('v')
                ->where('id(v)', '=', $id)
                ->return('v');
        })->first();

        if ($record === null) {
            return null;
        }

        $vertex = $record->v;
        $vertexType = VertexType::query()
            ->with('properties')
            ->where('age_label_name', $vertex->label)
            ->first();

        if ($vertexType === null) {
            return null;
        }

        $displayName = $this->displayNameResolver->resolve(
            $vertexType->show_property_name,
            $this->normalizeProperties($vertex->properties ?? []),
            $vertexType->properties,
        );

        return [
            'id' => (int) $vertex->id,
            'display_name' => $displayName !== '' ? $displayName : "(ID: {$id})",
            'type_label' => $vertexType->age_label_name,
            'type_name' => $vertexType->name,
        ];
    }

    /**
     * @param  list<string>|null  $typeLabels
     * @return list<array{id:int,display_name:string,type_label:string,type_name:string,start_vertex_id:int,end_vertex_id:int}>
     */
    public function searchEdges(string $query = '', ?array $typeLabels = null, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $query = trim($query);

        $edgeTypes = $this->resolveEdgeTypes($typeLabels);

        if ($edgeTypes->isEmpty()) {
            return [];
        }

        $results = [];
        $needle = Str::lower($query);
        $exactId = ctype_digit($query) ? (int) $query : null;

        foreach ($edgeTypes as $edgeType) {
            $rows = $this->loadEdgesByType($edgeType);

            foreach ($rows as $row) {
                $edge = $row->e;
                $start = $row->s;
                $end = $row->t;
                $id = (int) $edge->id;

                $startVertex = $edgeType->startVertex;
                $endVertex = $edgeType->endVertex;

                $startName = $this->displayNameResolver->resolve(
                    $startVertex?->show_property_name,
                    $this->normalizeProperties($start->properties ?? []),
                    $startVertex !== null ? $startVertex->properties : collect(),
                );
                $endName = $this->displayNameResolver->resolve(
                    $endVertex?->show_property_name,
                    $this->normalizeProperties($end->properties ?? []),
                    $endVertex !== null ? $endVertex->properties : collect(),
                );

                $startLabel = $startName !== '' ? $startName : 'ID:'.((int) $start->id);
                $endLabel = $endName !== '' ? $endName : 'ID:'.((int) $end->id);
                $displayName = "{$startLabel} → {$endLabel}";

                if (! $this->matchesEdgeQuery($needle, $exactId, $id, $displayName, $edgeType, $startLabel, $endLabel)) {
                    continue;
                }

                $results[] = [
                    'id' => $id,
                    'display_name' => $displayName,
                    'type_label' => $edgeType->age_label_name,
                    'type_name' => $edgeType->name,
                    'start_vertex_id' => (int) $start->id,
                    'end_vertex_id' => (int) $end->id,
                ];

                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * @return array{id:int,display_name:string,type_label:string,type_name:string,start_vertex_id:int,end_vertex_id:int}|null
     */
    public function findEdge(int $id): ?array
    {
        $record = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::BOTH, 'e')
                ->withMatchNode('t')
                ->where('id(e)', '=', $id)
                ->return(['e', 's', 't']);
        })->first();

        if ($record === null) {
            return null;
        }

        $edge = $record->e;
        $start = $record->s;
        $end = $record->t;

        $edgeType = EdgeType::query()
            ->with(['startVertex.properties', 'endVertex.properties'])
            ->where('age_label_name', $edge->label)
            ->first();

        if ($edgeType === null) {
            return null;
        }

        $startVertex = $edgeType->startVertex;
        $endVertex = $edgeType->endVertex;

        $startName = $this->displayNameResolver->resolve(
            $startVertex?->show_property_name,
            $this->normalizeProperties($start->properties ?? []),
            $startVertex !== null ? $startVertex->properties : collect(),
        );
        $endName = $this->displayNameResolver->resolve(
            $endVertex?->show_property_name,
            $this->normalizeProperties($end->properties ?? []),
            $endVertex !== null ? $endVertex->properties : collect(),
        );

        $startLabel = $startName !== '' ? $startName : 'ID:'.((int) $start->id);
        $endLabel = $endName !== '' ? $endName : 'ID:'.((int) $end->id);

        return [
            'id' => (int) $edge->id,
            'display_name' => "{$startLabel} → {$endLabel}",
            'type_label' => $edgeType->age_label_name,
            'type_name' => $edgeType->name,
            'start_vertex_id' => (int) $start->id,
            'end_vertex_id' => (int) $end->id,
        ];
    }

    /**
     * @param  list<string>|null  $typeLabels
     * @return Collection<int, VertexType>
     */
    private function resolveVertexTypes(?array $typeLabels): Collection
    {
        $query = VertexType::query()->with('properties')->orderBy('name');

        if ($typeLabels !== null && $typeLabels !== []) {
            $query->whereIn('age_label_name', $typeLabels);
        }

        return $query->get();
    }

    /**
     * @param  list<string>|null  $typeLabels
     * @return Collection<int, EdgeType>
     */
    private function resolveEdgeTypes(?array $typeLabels): Collection
    {
        $query = EdgeType::query()
            ->with(['startVertex.properties', 'endVertex.properties'])
            ->orderBy('name');

        if ($typeLabels !== null && $typeLabels !== []) {
            $query->whereIn('age_label_name', $typeLabels);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function loadVerticesByType(VertexType $vertexType): Collection
    {
        return $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexType) {
            return $builder->matchNode('v', $vertexType->age_label_name)
                ->return('v');
        })->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function loadEdgesByType(EdgeType $edgeType): Collection
    {
        $startLabel = $edgeType->startVertex?->age_label_name;
        $endLabel = $edgeType->endVertex?->age_label_name;

        if ($startLabel === null || $endLabel === null) {
            return collect();
        }

        return $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($edgeType, $startLabel, $endLabel) {
            return $builder
                ->matchNode('s', $startLabel)
                ->withMatchEdge(Direction::RIGHT, 'e', $edgeType->age_label_name)
                ->withMatchNode('t', $endLabel)
                ->return(['e', 's', 't']);
        })->get();
    }

    private function matchesVertexQuery(
        string $needle,
        ?int $exactId,
        int $id,
        string $displayName,
        VertexType $vertexType,
    ): bool {
        if ($needle === '') {
            return true;
        }

        if ($exactId !== null && $id === $exactId) {
            return true;
        }

        if (Str::contains(Str::lower($displayName), $needle)) {
            return true;
        }

        return Str::contains(Str::lower($vertexType->name), $needle)
            || Str::contains(Str::lower($vertexType->age_label_name), $needle);
    }

    private function matchesEdgeQuery(
        string $needle,
        ?int $exactId,
        int $id,
        string $displayName,
        EdgeType $edgeType,
        string $startLabel,
        string $endLabel,
    ): bool {
        if ($needle === '') {
            return true;
        }

        if ($exactId !== null && $id === $exactId) {
            return true;
        }

        return Str::contains(Str::lower($displayName), $needle)
            || Str::contains(Str::lower($startLabel), $needle)
            || Str::contains(Str::lower($endLabel), $needle)
            || Str::contains(Str::lower($edgeType->name), $needle)
            || Str::contains(Str::lower($edgeType->age_label_name), $needle);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProperties(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }

        if (is_object($properties)) {
            return (array) $properties;
        }

        return [];
    }

    private function graphConnection(): PostgresConnection
    {
        /** @var PostgresConnection $connection */
        $connection = DB::connection((string) config('cohistograph.app.graph.connection-name'));

        return $connection;
    }
}
