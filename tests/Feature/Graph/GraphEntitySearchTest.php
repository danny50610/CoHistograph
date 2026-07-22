<?php

namespace Tests\Feature\Graph;

use App\Enums\RevisionStatus;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GraphEntitySearchTest extends TestCase
{
    use DatabaseTransactions;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');
    }

    public function test_can_search_vertices_by_display_name(): void
    {
        $vertexType = VertexType::factory()->create([
            'name' => '人物',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $liBaiId = $this->createAgeVertex($vertexType->age_label_name, ['name' => '李白']);
        $this->createAgeVertex($vertexType->age_label_name, ['name' => '杜甫']);

        $response = $this->getJson(route('graph.search.vertices', ['q' => '李白']));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $liBaiId)
            ->assertJsonPath('data.0.display_name', '李白')
            ->assertJsonPath('data.0.type_label', $vertexType->age_label_name)
            ->assertJsonPath('data.0.type_name', '人物');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_resolve_vertex_by_id(): void
    {
        $vertexType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $vertexId = $this->createAgeVertex($vertexType->age_label_name, ['name' => '王維']);

        $this->getJson(route('graph.search.vertices', ['id' => $vertexId]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $vertexId)
            ->assertJsonPath('data.0.display_name', '王維');
    }

    public function test_can_filter_vertices_by_type_label(): void
    {
        $personType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($personType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $placeType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($placeType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $personId = $this->createAgeVertex($personType->age_label_name, ['name' => '長安人']);
        $this->createAgeVertex($placeType->age_label_name, ['name' => '長安']);

        $response = $this->getJson(route('graph.search.vertices', [
            'q' => '長安',
            'type' => $personType->age_label_name,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $personId);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_search_edges_by_endpoint_display_name(): void
    {
        $personType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($personType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $eventType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'title',
        ]);
        VertexProperty::factory()->for($eventType)->create([
            'name' => '標題',
            'age_property_name' => 'title',
            'locale' => null,
        ]);

        $edgeType = EdgeType::factory()->create([
            'name' => '參加',
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $personType->id,
            'end_vertex_id' => $eventType->id,
        ]);

        $personId = $this->createAgeVertex($personType->age_label_name, ['name' => '白居易']);
        $eventId = $this->createAgeVertex($eventType->age_label_name, ['title' => '曲江宴會']);
        $edgeId = $this->createAgeEdge($edgeType->age_label_name, $personId, $eventId);

        $response = $this->getJson(route('graph.search.edges', ['q' => '白居易']));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $edgeId)
            ->assertJsonPath('data.0.display_name', '白居易 → 曲江宴會')
            ->assertJsonPath('data.0.type_name', '參加')
            ->assertJsonPath('data.0.start_vertex_id', $personId)
            ->assertJsonPath('data.0.end_vertex_id', $eventId);
    }

    public function test_can_resolve_edge_by_id(): void
    {
        $personType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($personType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $eventType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'title',
        ]);
        VertexProperty::factory()->for($eventType)->create([
            'name' => '標題',
            'age_property_name' => 'title',
            'locale' => null,
        ]);

        $edgeType = EdgeType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $personType->id,
            'end_vertex_id' => $eventType->id,
        ]);

        $personId = $this->createAgeVertex($personType->age_label_name, ['name' => '蘇軾']);
        $eventId = $this->createAgeVertex($eventType->age_label_name, ['title' => '赤壁遊']);
        $edgeId = $this->createAgeEdge($edgeType->age_label_name, $personId, $eventId);

        $this->getJson(route('graph.search.edges', ['id' => $edgeId]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $edgeId)
            ->assertJsonPath('data.0.display_name', '蘇軾 → 赤壁遊');
    }

    public function test_revision_edit_page_includes_search_routes(): void
    {
        $user = User::factory()->create();
        $revision = Revision::query()->create([
            'title' => 'Draft revision',
            'description' => '',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('revisions.edit', $revision))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Revisions/Edit')
                ->where('routeSearchVertices', route('graph.search.vertices'))
                ->where('routeSearchEdges', route('graph.search.edges')));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createAgeVertex(string $label, array $properties = []): int
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
    private function createAgeEdge(string $label, int $startVertexId, int $endVertexId, array $properties = []): int
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
                    $builder = $builder->set($edgeProperties);
                }

                return $builder->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
