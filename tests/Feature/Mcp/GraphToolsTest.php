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
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_search_vertex_types_finds_by_query(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'name' => '歷史事件',
            'age_label_name' => $this->graphLabel('event'),
            'description' => '用於可明確界定時間範圍的歷史事件',
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchVertexTypesTool::class, [
            'query' => '事件',
            'limit' => 10,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($vertexType) {

            $json->where('total', 1)
                ->where('vertex_types.0.id', $vertexType->id)
                ->where('vertex_types.0.description', '用於可明確界定時間範圍的歷史事件')
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
            'description' => '用於表示人物實際參與某事件',
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
                ->where('edge_types.0.description', '用於表示人物實際參與某事件')
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

        $response->assertHasErrors(['Property year does not exist or is not a STRING type']);
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

    public function test_all_registered_tools_define_input_schemas(): void
    {
        $tools = (new \ReflectionClass(CoHistographServer::class))->getDefaultProperties()['tools'];
        $factory = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;

        foreach ($tools as $toolClass) {
            $schema = app($toolClass)->schema($factory);
            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema, "{$toolClass} schema should not be empty");
        }
    }

    public function test_search_vertex_types_includes_properties_and_pagination(): void
    {
        $user = User::factory()->createOne();
        $first = VertexType::factory()->createOne([
            'name' => 'AAA 類型',
            'age_label_name' => $this->graphLabel('aaa'),
        ]);
        $second = VertexType::factory()->createOne([
            'name' => 'BBB 類型',
            'age_label_name' => $this->graphLabel('bbb'),
        ]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $second->id,
            'name' => '名稱',
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchVertexTypesTool::class, [
            'query' => '類型',
            'include_properties' => true,
            'limit' => 1,
            'offset' => 1,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($second) {
            $json->where('total', 2)
                ->where('vertex_types.0.id', $second->id)
                ->where('vertex_types.0.properties.0.age_property_name', 'name')
                ->etc();

            return true;
        });
    }

    public function test_search_edge_types_can_include_properties_and_omit_vertices(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('person')]);
        $event = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        $edgeType = EdgeType::factory()->createOne([
            'name' => '出席',
            'age_label_name' => $this->graphLabel('attended'),
            'start_vertex_id' => $person->id,
            'end_vertex_id' => $event->id,
        ]);
        EdgeProperty::factory()->createOne([
            'edge_type_id' => $edgeType->id,
            'name' => '角色',
            'age_property_name' => 'role',
            'age_property_type' => PropertyType::String,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchEdgeTypesTool::class, [
            'query' => '出席',
            'include_properties' => true,
            'include_vertices' => false,
        ]);

        $response->assertOk();
        $response->assertSee('role');
        $response->assertDontSee($person->age_label_name);
        $response->assertStructuredContent(function (AssertableJson $json) use ($edgeType) {
            $json->where('total', 1)
                ->where('edge_types.0.id', $edgeType->id)
                ->where('edge_types.0.properties.0.age_property_name', 'role')
                ->missing('edge_types.0.vertex_pairs')
                ->etc();

            return true;
        });
    }

    public function test_search_vertices_lists_without_query_and_rejects_unknown_type(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        $ageId = $this->createAgeVertexWithProperties($vertexType->age_label_name, ['name' => '無查詢列出']);

        $list = CoHistographServer::actingAs($user)->tool(SearchVerticesTool::class, [
            'vertex_type_label' => $vertexType->age_label_name,
        ]);
        $list->assertOk();
        $list->assertStructuredContent(function (AssertableJson $json) use ($ageId) {
            $json->where('total', 1)->where('vertices.0.age_id', $ageId)->etc();

            return true;
        });

        CoHistographServer::actingAs($user)->tool(SearchVerticesTool::class, [
            'vertex_type_label' => 'missing_label_zzzz',
        ])->assertHasErrors(['Vertex type not found']);
    }

    public function test_search_edges_validation_and_end_vertex_filter(): void
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
            'age_property_name' => 'year',
            'age_property_type' => PropertyType::Integer,
        ]);

        $personId = $this->createAgeVertexWithProperties($person->age_label_name, ['name' => '甲']);
        $eventId = $this->createAgeVertexWithProperties($event->age_label_name, ['name' => '乙']);
        $edgeId = $this->createAgeEdgeWithProperties($edgeType->age_label_name, $personId, $eventId, []);

        CoHistographServer::actingAs($user)->tool(SearchEdgesTool::class, [])
            ->assertHasErrors(['At least one of']);

        CoHistographServer::actingAs($user)->tool(SearchEdgesTool::class, [
            'edge_type_label' => 'missing_edge_zzzz',
        ])->assertHasErrors(['Edge type not found']);

        CoHistographServer::actingAs($user)->tool(SearchEdgesTool::class, [
            'edge_type_label' => $edgeType->age_label_name,
            'query' => '1911',
            'property' => 'year',
        ])->assertHasErrors(['is not a STRING type']);

        $byEnd = CoHistographServer::actingAs($user)->tool(SearchEdgesTool::class, [
            'end_vertex_age_id' => $eventId,
        ]);
        $byEnd->assertOk();
        $byEnd->assertStructuredContent(function (AssertableJson $json) use ($edgeId) {
            $json->where('total', 1)->where('edges.0.age_id', $edgeId)->etc();

            return true;
        });
    }

    public function test_get_detail_not_found_and_incoming_neighbors(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('person')]);
        $event = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel('event')]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel('participated_in'),
            'start_vertex_id' => $person->id,
            'end_vertex_id' => $event->id,
        ]);

        $personId = $this->createAgeVertexWithProperties($person->age_label_name, ['name' => '丙']);
        $eventId = $this->createAgeVertexWithProperties($event->age_label_name, ['name' => '丁']);
        $edgeId = $this->createAgeEdgeWithProperties($edgeType->age_label_name, $personId, $eventId, []);

        CoHistographServer::actingAs($user)->tool(GetVertexDetailTool::class, [
            'age_id' => 999999999,
        ])->assertHasErrors(['Vertex not found']);

        CoHistographServer::actingAs($user)->tool(GetEdgeDetailTool::class, [
            'age_id' => 999999999,
        ])->assertHasErrors(['Edge not found']);

        CoHistographServer::actingAs($user)->tool(ListVertexNeighborsTool::class, [
            'age_id' => 999999999,
        ])->assertHasErrors(['Vertex not found']);

        $incoming = CoHistographServer::actingAs($user)->tool(ListVertexNeighborsTool::class, [
            'age_id' => $eventId,
            'direction' => 'incoming',
        ]);
        $incoming->assertOk();
        $incoming->assertStructuredContent(function (AssertableJson $json) use ($edgeId, $personId) {
            $json->where('neighbors.0.direction', 'incoming')
                ->where('neighbors.0.edge.age_id', $edgeId)
                ->where('neighbors.0.vertex.age_id', $personId)
                ->etc();

            return true;
        });
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
