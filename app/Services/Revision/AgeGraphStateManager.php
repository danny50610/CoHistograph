<?php

namespace App\Services\Revision;

use App\Models\EdgeType;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;

/**
 * 管理 Apache Age 圖數據庫狀態的查詢和緩存
 *
 * 負責：
 * - Schema 加載（VertexType 和 EdgeType）
 * - Age 頂點狀態查詢和緩存
 * - Age 邊狀態查詢和緩存
 * - 圖遍歷查詢
 */
class AgeGraphStateManager
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
     * @var array<int, array{exists:bool,type_label:string,properties:array<string,bool>,property_values:array<string, mixed>}>
     */
    private array $ageVertexCache = [];

    /**
     * @var array<int, array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>,property_values:array<string, mixed>}>
     */
    private array $ageEdgeCache = [];

    /**
     * @var array<string, list<array{age_id:int,type_label:string,properties:array<string,mixed>}>>
     */
    private array $verticesByTypeCache = [];

    public function bootSchemaMaps(): void
    {
        $this->vertexTypeByLabel = VertexType::query()
            ->with('properties')
            ->get()
            ->keyBy('age_label_name')
            ->all();

        $this->edgeTypeByLabel = EdgeType::query()
            ->with(['vertexPairs.startVertex', 'vertexPairs.endVertex', 'properties'])
            ->get()
            ->keyBy('age_label_name')
            ->all();
    }

    /**
     * @return array<string, VertexType>
     */
    public function getVertexTypeByLabel(): array
    {
        return $this->vertexTypeByLabel;
    }

    /**
     * @return array<string, EdgeType>
     */
    public function getEdgeTypeByLabel(): array
    {
        return $this->edgeTypeByLabel;
    }

    /**
     * 取得所有已加載的 Age Vertex 狀態
     *
     * @return array<int, array{exists:bool,type_label:string,properties:array<string,bool>,property_values:array<string, mixed>}>
     */
    public function getAllLoadedVertexStates(): array
    {
        return $this->ageVertexCache;
    }

    /**
     * 取得所有已加載的 Age Edge 狀態
     *
     * @return array<int, array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>,property_values:array<string, mixed>}>
     */
    public function getAllLoadedEdgeStates(): array
    {
        return $this->ageEdgeCache;
    }

    /**
     * @return array{exists:bool,type_label:string,properties:array<string,bool>,property_values:array<string, mixed>}
     */
    public function loadAgeVertexState(int $vertexId): array
    {
        if (isset($this->ageVertexCache[$vertexId])) {
            return $this->ageVertexCache[$vertexId];
        }

        $record = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexId) {
            return $builder
                ->matchNode('v')
                ->where('id(v)', '=', $vertexId)
                ->return('v');
        })->first();

        if ($record === null) {
            $cached = [
                'exists' => false,
                'type_label' => '',
                'properties' => [],
                'property_values' => [],
            ];
            $this->ageVertexCache[$vertexId] = $cached;

            return $cached;
        }

        $vertex = $record->v;
        $properties = [];
        $propertyValues = [];
        foreach ($this->normalizeProperties($vertex->properties ?? []) as $name => $value) {
            $properties[$name] = ! is_null($value);
            if (! is_null($value)) {
                $propertyValues[$name] = $value;
            }
        }

        $cached = [
            'exists' => true,
            'type_label' => (string) $vertex->label,
            'properties' => $properties,
            'property_values' => $propertyValues,
        ];

        $this->ageVertexCache[$vertexId] = $cached;

        return $cached;
    }

    /**
     * @return array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>,property_values:array<string, mixed>}
     */
    public function loadAgeEdgeState(int $edgeId): array
    {
        if (isset($this->ageEdgeCache[$edgeId])) {
            return $this->ageEdgeCache[$edgeId];
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
            $cached = [
                'exists' => false,
                'type_label' => '',
                'start' => 0,
                'end' => 0,
                'properties' => [],
                'property_values' => [],
            ];
            $this->ageEdgeCache[$edgeId] = $cached;

            return $cached;
        }

        $edge = $record->e;
        $start = $record->s;
        $end = $record->t;

        $properties = [];
        $propertyValues = [];
        foreach ($this->normalizeProperties($edge->properties ?? []) as $name => $value) {
            $properties[$name] = ! is_null($value);
            if (! is_null($value)) {
                $propertyValues[$name] = $value;
            }
        }

        $startId = (int) $start->id;
        $endId = (int) $end->id;

        $cached = [
            'exists' => true,
            'type_label' => (string) $edge->label,
            'start' => $startId,
            'end' => $endId,
            'properties' => $properties,
            'property_values' => $propertyValues,
        ];

        $this->ageEdgeCache[$edgeId] = $cached;

        return $cached;
    }

    /**
     * @return list<int>
     */
    public function loadConnectedEdgeIds(int $vertexId): array
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
     * @return list<int>
     */
    public function findEdgeIdsByEndpoints(string $edgeLabel, int $startVertexId, int $endVertexId): array
    {
        $rows = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($edgeLabel, $startVertexId, $endVertexId) {
            return $builder
                ->matchNode('s')
                ->withMatchEdge(Direction::RIGHT, 'e', $edgeLabel)
                ->withMatchNode('t')
                ->where('id(s)', '=', $startVertexId)
                ->where('id(t)', '=', $endVertexId)
                ->return('e');
        })->get();

        $edgeIds = [];
        foreach ($rows as $row) {
            $edgeId = (int) $row->e->id;
            $edgeIds[] = $edgeId;
            $this->loadAgeEdgeState($edgeId);
        }

        return array_values(array_unique($edgeIds));
    }

    /**
     * @return list<array{age_id:int,type_label:string,properties:array<string,mixed>}>
     */
    public function loadVerticesByType(string $vertexTypeLabel): array
    {
        if (isset($this->verticesByTypeCache[$vertexTypeLabel])) {
            return $this->verticesByTypeCache[$vertexTypeLabel];
        }

        $rows = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexTypeLabel) {
            return $builder
                ->matchNode('v', $vertexTypeLabel)
                ->return('v');
        })->get();

        $vertices = [];
        foreach ($rows as $row) {
            $vertex = $row->v;
            $ageId = (int) $vertex->id;
            $properties = $this->normalizeProperties($vertex->properties ?? []);

            $propertyFlags = [];
            $propertyValues = [];
            foreach ($properties as $name => $value) {
                $propertyFlags[$name] = ! is_null($value);
                if (! is_null($value)) {
                    $propertyValues[$name] = $value;
                }
            }

            $this->ageVertexCache[$ageId] = [
                'exists' => true,
                'type_label' => (string) $vertex->label,
                'properties' => $propertyFlags,
                'property_values' => $propertyValues,
            ];

            $vertices[] = [
                'age_id' => $ageId,
                'type_label' => (string) $vertex->label,
                'properties' => $properties,
            ];
        }

        $this->verticesByTypeCache[$vertexTypeLabel] = $vertices;

        return $vertices;
    }

    private function graphConnection(): PostgresConnection
    {
        /** @var PostgresConnection $connection */
        $connection = DB::connection((string) config('cohistograph.app.graph.connection-name'));

        return $connection;
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
}
