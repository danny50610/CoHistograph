<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Services\Revision\RevisionGraphPreviewBuilder;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RevisionGraphPreviewTest extends TestCase
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

    public function test_builder_visualizes_created_vertices_edges_and_properties(): void
    {
        $person = VertexType::factory()->createOne([
            'name' => '人物',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($person)->create([
            'name' => '姓名',
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);
        $event = VertexType::factory()->createOne([
            'name' => '活動',
            'age_label_name' => $this->graphLabel(),
        ]);
        $edgeType = EdgeType::factory()->createOne([
            'name' => '參加',
            'age_label_name' => $this->graphLabel(),
            'vertex_pairs' => [
                ['start_vertex_id' => $person->id, 'end_vertex_id' => $event->id],
            ],
        ]);

        $revision = $this->createDraftRevision(User::factory()->createOne(), [
            ['action' => 'create_vertex', 'vertex_type_label' => $person->age_label_name],
            ['action' => 'create_vertex', 'vertex_type_label' => $event->age_label_name],
            [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => 'name',
                'value' => '李白',
            ],
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_ref_order' => 0,
                'end_vertex_ref_order' => 1,
            ],
        ]);

        $graph = app(RevisionGraphPreviewBuilder::class)->build($revision->actions()->orderBy('order')->get());

        $this->assertCount(2, $graph['vertices']);
        $this->assertSame('李白', $graph['vertices'][0]['label']);
        $this->assertSame('created', $graph['vertices'][0]['status']);
        $this->assertSame('活動（新建 #2）', $graph['vertices'][1]['label']);
        $this->assertSame('created', $graph['vertices'][1]['status']);
        $this->assertSame('created', $graph['vertices'][0]['properties'][0]['status']);
        $this->assertSame('李白', $graph['vertices'][0]['properties'][0]['value']);

        $this->assertCount(1, $graph['edges']);
        $this->assertSame('參加', $graph['edges'][0]['label']);
        $this->assertSame('created', $graph['edges'][0]['status']);
        $this->assertSame('ref:0', $graph['edges'][0]['start_id']);
        $this->assertSame('ref:1', $graph['edges'][0]['end_id']);
    }

    public function test_builder_includes_existing_age_vertices_as_unchanged_context(): void
    {
        $person = VertexType::factory()->createOne([
            'name' => '人物',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($person)->create([
            'name' => '姓名',
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);
        $event = VertexType::factory()->createOne([
            'name' => '活動',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'title',
        ]);
        VertexProperty::factory()->for($event)->create([
            'name' => '標題',
            'age_property_name' => 'title',
            'age_property_type' => PropertyType::String,
        ]);
        $edgeType = EdgeType::factory()->createOne([
            'name' => '參加',
            'age_label_name' => $this->graphLabel(),
            'vertex_pairs' => [
                ['start_vertex_id' => $person->id, 'end_vertex_id' => $event->id],
            ],
        ]);

        $startId = $this->createAgeVertexWithProperties($person->age_label_name, ['name' => '杜甫']);
        $endId = $this->createAgeVertexWithProperties($event->age_label_name, ['title' => '曲江宴會']);

        $revision = $this->createDraftRevision(User::factory()->createOne(), [
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_age_id' => (string) $startId,
                'end_vertex_age_id' => (string) $endId,
            ],
        ]);

        $graph = app(RevisionGraphPreviewBuilder::class)->build($revision->actions()->orderBy('order')->get());

        $this->assertSame('杜甫', $graph['vertices'][0]['label']);
        $this->assertSame('unchanged', $graph['vertices'][0]['status']);
        $this->assertSame('曲江宴會', $graph['vertices'][1]['label']);
        $this->assertSame('unchanged', $graph['vertices'][1]['status']);
        $this->assertSame('created', $graph['edges'][0]['status']);
        $this->assertSame('age:'.$startId, $graph['edges'][0]['start_id']);
    }

    public function test_show_page_includes_visualization_tab_and_graph_json(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne([
            'name' => '人物',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($person)->create([
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => $person->age_label_name],
            [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => 'name',
                'value' => '王維',
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('revisions.show', $revision))
            ->assertOk()
            ->assertSee('視覺化')
            ->assertSee('revision-graph-tab', false);

        preg_match(
            '/<script type="application\/json" data-revision-graph-json>(.*?)<\/script>/s',
            $response->getContent(),
            $matches,
        );

        $this->assertNotEmpty($matches[1] ?? null);
        $graph = json_decode($matches[1], true);
        $this->assertIsArray($graph);
        $this->assertSame('王維', $graph['vertices'][0]['label'] ?? null);
        $this->assertSame('created', $graph['vertices'][0]['status'] ?? null);
    }

    public function test_edit_page_includes_graph_preview_props(): void
    {
        $user = User::factory()->createOne();
        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => 'person_label'],
        ]);

        $this->actingAs($user)
            ->get(route('revisions.edit', $revision))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Revisions/Edit')
                ->has('graphPreview.vertices')
                ->has('graphPreview.edges')
                ->where('routeGraphPreview', route('revisions.graph-preview', $revision))
            );
    }

    public function test_graph_preview_endpoint_returns_live_graph_for_unsaved_actions(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne([
            'name' => '人物',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($person)->create([
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);
        $revision = $this->createDraftRevision($user, []);

        $this->actingAs($user)
            ->postJson(route('revisions.graph-preview', $revision), [
                'title' => '即時視覺化',
                'description' => '',
                'actions' => [
                    [
                        'action' => 'create_vertex',
                        'vertex_type_label' => $person->age_label_name,
                    ],
                    [
                        'action' => 'create_vertex_property',
                        'target_ref_order' => 0,
                        'age_property_name' => 'name',
                        'value' => '白居易',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('vertices.0.label', '白居易')
            ->assertJsonPath('vertices.0.status', 'created')
            ->assertJsonPath('edges', []);
    }

    public function test_non_owner_cannot_request_graph_preview(): void
    {
        $owner = User::factory()->createOne();
        $other = User::factory()->createOne();
        $revision = $this->createDraftRevision($owner, []);

        $this->actingAs($other)
            ->postJson(route('revisions.graph-preview', $revision), [
                'title' => '即時視覺化',
                'actions' => [],
            ])
            ->assertForbidden();
    }

    public function test_admin_show_page_includes_visualization_tab(): void
    {
        $reviewer = User::factory()->createOne();
        $reviewer->givePermission('revision.review');
        $owner = User::factory()->createOne();
        $revision = $this->createDraftRevision($owner, [
            ['action' => 'create_vertex', 'vertex_type_label' => 'person'],
        ]);
        $revision->update(['status' => RevisionStatus::PendingReview]);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.show', $revision))
            ->assertOk()
            ->assertSee('視覺化')
            ->assertSee('操作清單')
            ->assertSee('revision-graph-tab', false);
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     */
    private function createDraftRevision(User $user, array $actions): Revision
    {
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        foreach ($actions as $index => $action) {
            $revision->actions()->create([
                'order' => $index,
                'action' => $action['action'],
                'target_age_id' => $action['target_age_id'] ?? null,
                'target_ref_order' => $action['target_ref_order'] ?? null,
                'vertex_type_label' => $action['vertex_type_label'] ?? null,
                'edge_type_label' => $action['edge_type_label'] ?? null,
                'start_vertex_age_id' => $action['start_vertex_age_id'] ?? null,
                'start_vertex_ref_order' => $action['start_vertex_ref_order'] ?? null,
                'end_vertex_age_id' => $action['end_vertex_age_id'] ?? null,
                'end_vertex_ref_order' => $action['end_vertex_ref_order'] ?? null,
                'age_property_name' => $action['age_property_name'] ?? null,
                'value' => $action['value'] ?? null,
            ]);
        }

        return $revision;
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

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
