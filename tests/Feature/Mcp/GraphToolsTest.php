<?php

namespace Tests\Feature\Mcp;

use App\Enums\PropertyType;
use App\Mcp\Servers\CoHistographServer;
use App\Mcp\Tools\Graph\GetEdgeDetailTool;
use App\Mcp\Tools\Graph\GetVertexDetailTool;
use App\Mcp\Tools\Graph\ListVertexNeighborsTool;
use App\Mcp\Tools\Graph\SearchEdgesTool;
use App\Mcp\Tools\Graph\SearchVerticesTool;
use App\Mcp\Tools\Schema\SearchEdgeTypesTool;
use App\Mcp\Tools\Schema\SearchVertexTypesTool;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GraphToolsTest extends TestCase
{
    use DatabaseTransactions;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }
    }

    public function test_unauthenticated_web_mcp_request_returns_401(): void
    {
        $this->postJson('/mcp/cohistograph', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_search_vertex_types_returns_usage_guidelines(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'name' => '歷史事件',
            'age_label_name' => $this->graphLabel('event'),
            'usage_guidelines' => '用於可明確界定時間範圍的歷史事件',
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchVertexTypesTool::class, [
            'query' => '事件',
            'limit' => 10,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($vertexType) {

            $json->where('total', 1)
                ->where('vertex_types.0.id', $vertexType->id)
                ->where('vertex_types.0.usage_guidelines', '用於可明確界定時間範圍的歷史事件')
                ->etc();

            return true;
        });
    }

    public function test_search_edge_types_filters_by_endpoint_labels(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('person')]);
        $event = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        $edgeType = EdgeType::factory()->createOne([
            'name' => '參與',
            'age_label_name' => $this->graphLabel('participated_in'),
            'usage_guidelines' => '用於表示人物實際參與某事件',
            'start_vertex_id' => $person->id,
            'end_vertex_id' => $event->id,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchEdgeTypesTool::class, [
            'query' => '參與',
            'start_vertex_type_label' => $person->age_label_name,
            'end_vertex_type_label' => $event->age_label_name,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($edgeType) {

            $json->where('total', 1)
                ->where('edge_types.0.id', $edgeType->id)
                ->where('edge_types.0.usage_guidelines', '用於表示人物實際參與某事件')
                ->etc();

            return true;
        });
    }

    public function test_search_vertices_finds_by_string_property(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $ageId = $this->createAgeVertexWithProperties($vertexType->age_label_name, ['name' => '辛亥革命']);

        $response = CoHistographServer::actingAs($user)->tool(SearchVerticesTool::class, [
            'vertex_type_label' => $vertexType->age_label_name,
            'query' => '辛亥',
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($ageId) {

            $json->where('total', 1)
                ->where('vertices.0.age_id', $ageId)
                ->where('vertices.0.properties.name', '辛亥革命')
                ->etc();

            return true;
        });
    }

    public function test_search_vertices_rejects_non_string_property(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'year',
            'age_property_type' => PropertyType::Integer,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchVerticesTool::class, [
            'vertex_type_label' => $vertexType->age_label_name,
            'query' => '1911',
            'property' => 'year',
        ]);

        $response->assertHasErrors(['屬性 year 不存在或不是 STRING 類型']);
    }

    public function test_get_vertex_detail_neighbors_and_edge_search(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('person')]);
        $event = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel('participated_in'),
            'start_vertex_id' => $person->id,
            'end_vertex_id' => $event->id,
        ]);
        EdgeProperty::factory()->createOne([
            'edge_type_id' => $edgeType->id,
            'age_property_name' => 'role',
            'age_property_type' => PropertyType::String,
        ]);

        $personId = $this->createAgeVertexWithProperties($person->age_label_name, ['name' => '孫文']);
        $eventId = $this->createAgeVertexWithProperties($event->age_label_name, ['name' => '辛亥革命']);
        $edgeId = $this->createAgeEdgeWithProperties($edgeType->age_label_name, $personId, $eventId, ['role' => '領導']);

        CoHistographServer::actingAs($user)->tool(GetVertexDetailTool::class, [
            'age_id' => $personId,
        ])->assertOk()->assertSee($person->age_label_name);

        $neighbors = CoHistographServer::actingAs($user)->tool(ListVertexNeighborsTool::class, [
            'age_id' => $personId,
            'direction' => 'outgoing',
        ]);
        $neighbors->assertOk();
        $neighbors->assertStructuredContent(function (AssertableJson $json) use ($edgeId) {

            $json->where('neighbors.0.edge.age_id', $edgeId)->etc();

            return true;
        });

        $edgeDetail = CoHistographServer::actingAs($user)->tool(GetEdgeDetailTool::class, [
            'age_id' => $edgeId,
        ]);
        $edgeDetail->assertOk();
        $edgeDetail->assertStructuredContent(function (AssertableJson $json) use ($personId, $eventId) {

            $json->where('start_vertex.age_id', $personId)
                ->where('end_vertex.age_id', $eventId)
                ->etc();

            return true;
        });

        $searchEdges = CoHistographServer::actingAs($user)->tool(SearchEdgesTool::class, [
            'edge_type_label' => $edgeType->age_label_name,
            'start_vertex_age_id' => $personId,
            'query' => '領導',
            'property' => 'role',
        ]);
        $searchEdges->assertOk();
        $searchEdges->assertStructuredContent(function (AssertableJson $json) {

            $json->where('total', 1)->etc();

            return true;
        });
    }

    public function test_passport_acting_as_can_call_authenticated_tool(): void
    {
        $user = User::factory()->createOne();
        Passport::actingAs($user, ['mcp:use']);

        VertexType::factory()->createOne([
            'name' => '人物',
            'age_label_name' => $this->graphLabel('person'),
        ]);

        CoHistographServer::tool(SearchVertexTypesTool::class, [
            'query' => '人物',
        ])->assertOk()->assertSee('人物');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createAgeVertexWithProperties(string $label, array $properties): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $properties) {
                return $builder->createNode('v', $label, $properties)->return('v');
            })
            ->first();

        return (int) $result->v->id;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createAgeEdgeWithProperties(string $label, int $startVertexId, int $endVertexId, array $properties): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $startVertexId, $endVertexId, $properties) {
                $builder = $builder
                    ->matchNode('s')
                    ->where('id(s)', '=', $startVertexId)
                    ->matchNode('t')
                    ->where('id(t)', '=', $endVertexId)
                    ->createRaw("(s)-[e:{$label}]->(t)");

                if ($properties !== []) {
                    $edgeProperties = [];
                    foreach ($properties as $key => $value) {
                        $edgeProperties["e.{$key}"] = $value;
                    }
                    $builder->set($edgeProperties);
                }

                return $builder->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }

    private function graphLabel(string $prefix = 'label'): string
    {
        return $prefix.'_'.fake()->unique()->lexify('??????');
    }
}
