<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionSubmitValidationTest extends TestCase
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

    public function test_valid_revision_can_submit_to_pending_review(): void
    {
        $user = User::factory()->createOne();

        $personType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $eventType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $personType->id,
            'end_vertex_id' => $eventType->id,
        ]);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_vertex',
                'vertex_type_label' => $personType->age_label_name,
            ],
            [
                'action' => 'create_vertex',
                'vertex_type_label' => $eventType->age_label_name,
            ],
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_ref_order' => 0,
                'end_vertex_ref_order' => 1,
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::PendingReview->value,
        ]);
    }

    public function test_submit_fails_when_actions_are_empty(): void
    {
        $user = User::factory()->createOne();
        $revision = $this->createDraftRevision($user, []);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Draft->value,
        ]);
    }

    public function test_submit_fails_when_create_edge_vertices_do_not_exist(): void
    {
        $user = User::factory()->createOne();

        $personType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $eventType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $personType->id,
            'end_vertex_id' => $eventType->id,
        ]);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_edge',
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_age_id' => 999999,
                'end_vertex_age_id' => 999998,
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('START_VERTEX_NOT_FOUND', $codes, true)
                    && in_array('END_VERTEX_NOT_FOUND', $codes, true);
            });

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Draft->value,
        ]);
    }

    public function test_submit_fails_when_delete_vertex_has_remaining_edges(): void
    {
        $user = User::factory()->createOne();

        $personType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $eventType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $edgeType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $personType->id,
            'end_vertex_id' => $eventType->id,
        ]);

        $personId = $this->createAgeVertex($personType->age_label_name);
        $eventId = $this->createAgeVertex($eventType->age_label_name);
        $this->createAgeEdge($edgeType->age_label_name, $personId, $eventId);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'delete_vertex',
                'target_age_id' => $personId,
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('VERTEX_HAS_REMAINING_EDGES', $codes, true);
            });

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Draft->value,
        ]);
    }

    public function test_submit_fails_when_property_value_type_mismatch(): void
    {
        $user = User::factory()->createOne();

        $personType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $personType->id,
            'age_property_name' => 'birth_year',
            'age_property_type' => 'INTEGER',
        ]);

        $personId = $this->createAgeVertex($personType->age_label_name);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_vertex_property',
                'target_age_id' => $personId,
                'age_property_name' => 'birth_year',
                'value' => 'not-an-integer',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[0] ?? [], 'code');

                return in_array('PROPERTY_TYPE_MISMATCH', $codes, true);
            });
    }

    public function test_submit_reports_dependency_invalid_when_previous_action_is_invalid(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'name',
            'age_property_type' => 'STRING',
        ]);

        $revision = $this->createDraftRevision($user, [
            [
                'action' => 'create_vertex',
                'vertex_type_label' => 'missing_vertex_type',
            ],
            [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => 'name',
                'value' => 'Danny',
            ],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionHas('revision_action_error_details', function (array $details): bool {
                $codes = array_column($details[1] ?? [], 'code');

                return in_array('DEPENDENCY_INVALID', $codes, true);
            });
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
                return $builder
                    ->createNode('v', $label)
                    ->return('v');
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

    private function actAs(User $user): static
    {
        /** @var AuthenticatableContract $authUser */
        $authUser = $user;

        return $this->actingAs($authUser);
    }

    // -------------------------------------------------------------------------
    // Happy-path tests: Cases 2-10 from .spec/cases.json
    // -------------------------------------------------------------------------

    /**
     * Case 2: 新增曲目及演唱關係（首次提交驗證通過）
     * Actions: create_vertex, create_vertex_property, create_edge, create_edge_property
     */
    public function test_revision_with_create_vertex_property_and_edge_property_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);

        $revision = $this->createDraftRevision($user, [
            // order 0: create track vertex (ref:0)
            ['action' => 'create_vertex', 'vertex_type_label' => $schema['track']->age_label_name],
            // order 1: set title on ref:0
            ['action' => 'create_vertex_property', 'target_ref_order' => 0, 'age_property_name' => 'title', 'value' => 'Glitch Star'],
            // order 2: create video_clip vertex (ref:2)
            ['action' => 'create_vertex', 'vertex_type_label' => $schema['video_clip']->age_label_name],
            // order 3: set clip_code on ref:2
            ['action' => 'create_vertex_property', 'target_ref_order' => 2, 'age_property_name' => 'clip_code', 'value' => 'XYZ099'],
            // order 4: linked_clip from ref:0 to ref:2
            ['action' => 'create_edge', 'edge_type_label' => $schema['linked_clip']->age_label_name, 'start_vertex_ref_order' => 0, 'end_vertex_ref_order' => 2],
            // order 5: performs from existing artist to ref:0
            ['action' => 'create_edge', 'edge_type_label' => $schema['performs']->age_label_name, 'start_vertex_age_id' => $artistId, 'end_vertex_ref_order' => 0],
            // order 6: set track_order on ref:5
            ['action' => 'create_edge_property', 'target_ref_order' => 5, 'age_property_name' => 'track_order', 'value' => '1'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 3: 更正演唱順序排名
     * Actions: update_edge_property
     */
    public function test_revision_with_update_edge_property_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artist1Id = $this->createAgeVertex($schema['artist']->age_label_name);
        $artist2Id = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $edge1Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist1Id, $trackId, ['track_order' => 2]);
        $edge2Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist2Id, $trackId, ['track_order' => 1]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'update_edge_property', 'target_age_id' => $edge1Id, 'age_property_name' => 'track_order', 'value' => '1'],
            ['action' => 'update_edge_property', 'target_age_id' => $edge2Id, 'age_property_name' => 'track_order', 'value' => '2'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 4: 補充缺失的頂點與邊屬性
     * Actions: create_vertex_property (on existing vertex), create_edge_property (on existing edge)
     */
    public function test_revision_adding_missing_properties_to_existing_vertices_and_edges_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $edgeId = $this->createAgeEdge($schema['performs']->age_label_name, $artistId, $trackId);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex_property', 'target_age_id' => $artistId, 'age_property_name' => 'nickname', 'value' => 'Vorryn'],
            ['action' => 'create_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'track_order', 'value' => '1'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 5: 替換影音片段連結
     * Actions: delete_edge, delete_vertex, create_vertex, create_vertex_property, create_edge
     * 必須先刪邊再刪頂點
     */
    public function test_revision_replacing_linked_clip_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $oldClipId = $this->createAgeVertex($schema['video_clip']->age_label_name);
        $linkedClipEdgeId = $this->createAgeEdge($schema['linked_clip']->age_label_name, $trackId, $oldClipId);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'delete_edge', 'target_age_id' => $linkedClipEdgeId],
            ['action' => 'delete_vertex', 'target_age_id' => $oldClipId],
            ['action' => 'create_vertex', 'vertex_type_label' => $schema['video_clip']->age_label_name],
            ['action' => 'create_vertex_property', 'target_ref_order' => 2, 'age_property_name' => 'clip_code', 'value' => 'DEF088'],
            ['action' => 'create_edge', 'edge_type_label' => $schema['linked_clip']->age_label_name, 'start_vertex_age_id' => $trackId, 'end_vertex_ref_order' => 2],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 6: 完整移除藝術家（正確順序，含所有相關邊）
     * Actions: delete_edge_property, delete_edge, delete_vertex_property, delete_vertex
     */
    public function test_revision_fully_removing_artist_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $bandId = $this->createAgeVertex($schema['band']->age_label_name);
        $artistId = $this->createAgeVertexWithProperties($schema['artist']->age_label_name, ['nickname' => 'Vorryn']);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $track2Id = $this->createAgeVertex($schema['track']->age_label_name);
        $belongsToId = $this->createAgeEdge($schema['belongs_to']->age_label_name, $artistId, $bandId);
        $performs1Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1]);
        $performs2Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $track2Id, ['track_order' => 1]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'delete_edge_property', 'target_age_id' => $performs1Id, 'age_property_name' => 'track_order'],
            ['action' => 'delete_edge_property', 'target_age_id' => $performs2Id, 'age_property_name' => 'track_order'],
            ['action' => 'delete_edge', 'target_age_id' => $performs1Id],
            ['action' => 'delete_edge', 'target_age_id' => $performs2Id],
            ['action' => 'delete_edge', 'target_age_id' => $belongsToId],
            ['action' => 'delete_vertex_property', 'target_age_id' => $artistId, 'age_property_name' => 'nickname'],
            ['action' => 'delete_vertex', 'target_age_id' => $artistId],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 7: 批量修正資料品質
     * Actions: update_vertex_property, update_edge_property (batch)
     */
    public function test_revision_batch_updating_properties_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artist1Id = $this->createAgeVertexWithProperties($schema['artist']->age_label_name, ['nickname' => 'Luminea']);
        $artist2Id = $this->createAgeVertexWithProperties($schema['artist']->age_label_name, ['nickname' => 'vorryn']);
        $trackId = $this->createAgeVertex($schema['track']->age_label_name);
        $track2Id = $this->createAgeVertex($schema['track']->age_label_name);
        $performs1Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist1Id, $trackId, ['track_order' => 2]);
        $performs2Id = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artist2Id, $track2Id, ['track_order' => 2]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'update_vertex_property', 'target_age_id' => $artist1Id, 'age_property_name' => 'nickname', 'value' => 'Luminae'],
            ['action' => 'update_vertex_property', 'target_age_id' => $artist2Id, 'age_property_name' => 'nickname', 'value' => 'Vorryn'],
            ['action' => 'update_edge_property', 'target_age_id' => $performs1Id, 'age_property_name' => 'track_order', 'value' => '1'],
            ['action' => 'update_edge_property', 'target_age_id' => $performs2Id, 'age_property_name' => 'track_order', 'value' => '1'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 8: 補充曲目發行年份（INTEGER）與演唱掛名（STRING）
     * Actions: create_vertex_property (INTEGER), create_edge_property (STRING)
     */
    public function test_revision_adding_integer_and_string_properties_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertexWithProperties($schema['track']->age_label_name, ['title' => 'Nebula Call']);
        $edgeId = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1]);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'create_vertex_property', 'target_age_id' => $trackId, 'age_property_name' => 'release_year', 'value' => '2023'],
            ['action' => 'create_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'featuring', 'value' => 'feat. Vorryn'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 9: 修正曲目發行年份（INTEGER）與演唱掛名（STRING）
     * Actions: update_vertex_property (INTEGER), update_edge_property (STRING)
     */
    public function test_revision_correcting_integer_and_string_properties_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertexWithProperties($schema['track']->age_label_name, ['title' => 'Nebula Call', 'release_year' => 2022]);
        $edgeId = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1, 'featuring' => 'feat. Voryn']);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'update_vertex_property', 'target_age_id' => $trackId, 'age_property_name' => 'release_year', 'value' => '2023'],
            ['action' => 'update_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'featuring', 'value' => 'feat. Vorryn'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    /**
     * Case 10: 移除曲目發行年份與演唱掛名
     * Actions: delete_vertex_property, delete_edge_property
     */
    public function test_revision_deleting_properties_passes_validation(): void
    {
        $user = User::factory()->createOne();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);
        $trackId = $this->createAgeVertexWithProperties($schema['track']->age_label_name, ['title' => 'Nebula Call', 'release_year' => 2023]);
        $edgeId = $this->createAgeEdgeWithProperties($schema['performs']->age_label_name, $artistId, $trackId, ['track_order' => 1, 'featuring' => 'feat. Vorryn']);

        $revision = $this->createDraftRevision($user, [
            ['action' => 'delete_vertex_property', 'target_age_id' => $trackId, 'age_property_name' => 'release_year'],
            ['action' => 'delete_edge_property', 'target_age_id' => $edgeId, 'age_property_name' => 'featuring'],
        ]);

        $this->actAs($user)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision))
            ->assertSessionMissing('revision_action_errors');

        $this->assertDatabaseHas('revisions', ['id' => $revision->id, 'status' => RevisionStatus::PendingReview->value]);
    }

    // -------------------------------------------------------------------------
    // Schema helpers
    // -------------------------------------------------------------------------

    /**
     * 建立完整的測試 Schema（對應 spec 中的 preset_data schema）
     * 使用唯一的 label 名稱避免測試間衝突。
     *
     * @return array{band: VertexType, artist: VertexType, track: VertexType, video_clip: VertexType, belongs_to: EdgeType, performs: EdgeType, linked_clip: EdgeType}
     */
    private function setupFullSchema(): array
    {
        $bandType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $artistType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $trackType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);
        $videoClipType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        VertexProperty::factory()->createOne(['vertex_type_id' => $artistType->id, 'age_property_name' => 'nickname', 'age_property_type' => PropertyType::String]);
        VertexProperty::factory()->createOne(['vertex_type_id' => $trackType->id, 'age_property_name' => 'title', 'age_property_type' => PropertyType::String]);
        VertexProperty::factory()->createOne(['vertex_type_id' => $trackType->id, 'age_property_name' => 'release_year', 'age_property_type' => PropertyType::Integer]);
        VertexProperty::factory()->createOne(['vertex_type_id' => $videoClipType->id, 'age_property_name' => 'clip_code', 'age_property_type' => PropertyType::String]);

        $belongsToType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $bandType->id,
        ]);
        $performsType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $artistType->id,
            'end_vertex_id' => $trackType->id,
        ]);
        EdgeProperty::factory()->createOne(['edge_type_id' => $performsType->id, 'age_property_name' => 'track_order', 'age_property_type' => PropertyType::Integer]);
        EdgeProperty::factory()->createOne(['edge_type_id' => $performsType->id, 'age_property_name' => 'featuring', 'age_property_type' => PropertyType::String]);
        $linkedClipType = EdgeType::factory()->createOne([
            'age_label_name' => $this->graphLabel(),
            'start_vertex_id' => $trackType->id,
            'end_vertex_id' => $videoClipType->id,
        ]);

        return [
            'band' => $bandType,
            'artist' => $artistType,
            'track' => $trackType,
            'video_clip' => $videoClipType,
            'belongs_to' => $belongsToType,
            'performs' => $performsType,
            'linked_clip' => $linkedClipType,
        ];
    }

    // -------------------------------------------------------------------------
    // Additional AGE helpers
    // -------------------------------------------------------------------------

    private function createAgeVertexWithProperties(string $label, array $properties): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $properties) {
                return $builder->createNode('v', $label, $properties)->return('v');
            })
            ->first();

        return (int) $result->v->id;
    }

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
                    $builder = $builder->set($edgeProperties);
                }

                return $builder->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }
}
