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
     * @var array<int, array{exists:bool,type_label:string,properties:array<string,bool>}>
     */
    private array $ageVertexCache = [];

    /**
     * @var array<int, array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>}>
     */
    private array $ageEdgeCache = [];

    public function bootSchemaMaps(): void
    {
        $this->vertexTypeByLabel = VertexType::query()
            ->with('properties')
            ->get()
            ->keyBy('age_label_name')
            ->all();

        $this->edgeTypeByLabel = EdgeType::query()
            ->with(['startVertex', 'endVertex', 'properties'])
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
     * @return array<int, array{exists:bool,type_label:string,properties:array<string,bool>}>
     */
    public function getAllLoadedVertexStates(): array
    {
        return $this->ageVertexCache;
    }

    /**
     * 取得所有已加載的 Age Edge 狀態
     *
     * @return array<int, array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>}>
     */
    public function getAllLoadedEdgeStates(): array
    {
        return $this->ageEdgeCache;
    }

    /**
     * @return array{exists:bool,type_label:string,properties:array<string,bool>}
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
            ];
            $this->ageVertexCache[$vertexId] = $cached;

            return $cached;
        }

        $vertex = $record->v;
        $properties = [];
        foreach ($this->normalizeProperties($vertex->properties ?? []) as $name => $value) {
            $properties[$name] = ! is_null($value);
        }

        $cached = [
            'exists' => true,
            'type_label' => (string) $vertex->label,
            'properties' => $properties,
        ];

        $this->ageVertexCache[$vertexId] = $cached;

        return $cached;
    }

    /**
     * @return array{exists:bool,type_label:string,start:int,end:int,properties:array<string,bool>}
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
            ];
            $this->ageEdgeCache[$edgeId] = $cached;

            return $cached;
        }

        $edge = $record->e;
        $start = $record->s;
        $end = $record->t;

        $properties = [];
        foreach ($this->normalizeProperties($edge->properties ?? []) as $name => $value) {
            $properties[$name] = ! is_null($value);
        }

        $startId = (int) $start->id;
        $endId = (int) $end->id;

        $cached = [
            'exists' => true,
            'type_label' => (string) $edge->label,
            'start' => $startId,
            'end' => $endId,
            'properties' => $properties,
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
