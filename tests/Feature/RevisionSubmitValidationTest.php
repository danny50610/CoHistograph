<?php

namespace Tests\Feature;

use App\Enums\RevisionStatus;
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
}
