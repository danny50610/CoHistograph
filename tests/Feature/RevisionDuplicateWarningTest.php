<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Services\Revision\RevisionValidationService;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionDuplicateWarningTest extends TestCase
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

    public function test_duplicate_edge_in_revision_emits_warning_but_allows_submit(): void
    {
        $user = User::factory()->createOne();
        $startType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $endType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'vertex_pairs' => [
                ['start_vertex_id' => $startType->id, 'end_vertex_id' => $endType->id],
            ],
        ]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => $startType->age_label_name],
            ['action' => 'create_vertex', 'vertex_type_label' => $endType->age_label_name],
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_ref_order' => 0,
                'end_vertex_ref_order' => 1,
            ],
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_ref_order' => 0,
                'end_vertex_ref_order' => 1,
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->load('actions'));

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasAnyWarning());
        $this->assertSame('DUPLICATE_EDGE', $result->actionWarnings()[2][0]['code'] ?? null);
        $this->assertSame('DUPLICATE_EDGE', $result->actionWarnings()[3][0]['code'] ?? null);

        $this->actingAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::PendingReview->value,
        ]);

        $revision->refresh();
        $this->assertTrue((bool) $revision->last_validation_is_valid);
        $this->assertSame('檢查通過（有警告）', $revision->last_validation_summary);
        $this->assertSame('DUPLICATE_EDGE', $revision->last_validation_action_warnings[2][0]['code'] ?? null);
        $this->assertArrayHasKey('meta', $revision->last_validation_action_warnings[2][0] ?? []);
    }

    public function test_duplicate_edge_against_age_emits_warning(): void
    {
        $user = User::factory()->createOne();
        $startType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $endType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'vertex_pairs' => [
                ['start_vertex_id' => $startType->id, 'end_vertex_id' => $endType->id],
            ],
        ]);

        $startId = $this->createAgeVertex($startType->age_label_name);
        $endId = $this->createAgeVertex($endType->age_label_name);
        $existingEdgeId = $this->createAgeEdge($edgeType->age_label_name, $startId, $endId);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_age_id' => $startId,
                'end_vertex_age_id' => $endId,
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->load('actions'));

        $this->assertTrue($result->isValid());
        $this->assertSame('DUPLICATE_EDGE', $result->actionWarnings()[0][0]['code'] ?? null);
        $this->assertSame(
            $existingEdgeId,
            $result->actionWarnings()[0][0]['meta']['conflicts'][0]['existing_edge_age_id'] ?? null,
        );
    }

    public function test_delete_then_recreate_edge_does_not_warn(): void
    {
        $user = User::factory()->createOne();
        $startType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $endType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'vertex_pairs' => [
                ['start_vertex_id' => $startType->id, 'end_vertex_id' => $endType->id],
            ],
        ]);

        $startId = $this->createAgeVertex($startType->age_label_name);
        $endId = $this->createAgeVertex($endType->age_label_name);
        $existingEdgeId = $this->createAgeEdge($edgeType->age_label_name, $startId, $endId);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'delete_edge', 'target_age_id' => $existingEdgeId],
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_age_id' => $startId,
                'end_vertex_age_id' => $endId,
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->load('actions'));

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasAnyWarning());
    }

    public function test_duplicate_vertex_display_name_emits_warning_but_allows_submit(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $existingId = $this->createAgeVertexWithProperties($vertexType->age_label_name, ['name' => '李白']);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => $vertexType->age_label_name],
            [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => 'name',
                'value' => '李白',
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->load('actions'));

        $this->assertTrue($result->isValid());
        $this->assertSame('DUPLICATE_VERTEX', $result->actionWarnings()[0][0]['code'] ?? null);
        $this->assertSame('李白', $result->actionWarnings()[0][0]['meta']['display_name'] ?? null);
        $this->assertSame(
            $existingId,
            $result->actionWarnings()[0][0]['meta']['conflicts'][0]['existing_vertex_age_id'] ?? null,
        );

        $this->actingAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::PendingReview->value,
        ]);
    }

    public function test_duplicate_vertex_within_revision_emits_warning(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => $vertexType->age_label_name],
            [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => 'name',
                'value' => '杜甫',
            ],
            ['action' => 'create_vertex', 'vertex_type_label' => $vertexType->age_label_name],
            [
                'action' => 'create_vertex_property',
                'target_ref_order' => 2,
                'age_property_name' => 'name',
                'value' => '杜甫',
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->load('actions'));

        $this->assertTrue($result->isValid());
        $this->assertSame('DUPLICATE_VERTEX', $result->actionWarnings()[0][0]['code'] ?? null);
        $this->assertSame('DUPLICATE_VERTEX', $result->actionWarnings()[2][0]['code'] ?? null);
    }

    public function test_empty_display_name_does_not_warn(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex', 'vertex_type_label' => $vertexType->age_label_name],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->load('actions'));

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasAnyWarning());
    }

    public function test_validate_draft_json_includes_warnings(): void
    {
        $user = User::factory()->createOne();
        $startType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $endType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'vertex_pairs' => [
                ['start_vertex_id' => $startType->id, 'end_vertex_id' => $endType->id],
            ],
        ]);

        $startId = $this->createAgeVertex($startType->age_label_name);
        $endId = $this->createAgeVertex($endType->age_label_name);
        $this->createAgeEdge($edgeType->age_label_name, $startId, $endId);

        $revision = Revision::query()->create([
            'title' => '草稿警告',
            'description' => null,
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('revisions.validate', $revision), [
                'title' => '草稿警告',
                'description' => null,
                'actions' => [
                    [
                        'action' => 'create_edge',
                        'edge_type_label' => $edgeType->age_label_name,
                        'start_vertex_age_id' => $startId,
                        'end_vertex_age_id' => $endId,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('summary', '檢查通過（有警告）')
            ->assertJsonPath('action_warnings.0.0.code', 'DUPLICATE_EDGE');
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

    private function createAgeVertex(string $label): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label) {
                return $builder->createNode('v', $label)->return('v');
            })
            ->first();

        return (int) $result->v->id;
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

    private function createAgeEdge(string $label, int $startVertexId, int $endVertexId): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $startVertexId, $endVertexId) {
                return $builder
                    ->matchNode('s')
                    ->where('id(s)', '=', $startVertexId)
                    ->matchNode('t')
                    ->where('id(t)', '=', $endVertexId)
                    ->createRaw("(s)-[e:{$label}]->(t)")
                    ->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
