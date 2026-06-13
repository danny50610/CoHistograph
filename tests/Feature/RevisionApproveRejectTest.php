<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionReviewAction;
use App\Enums\RevisionStatus;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\RevisionReview;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionApproveRejectTest extends TestCase
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

    public function test_reviewer_can_approve_valid_pending_revision(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex', 'vertex_type_label' => $vertexType->age_label_name],
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.approve', $revision))
            ->assertRedirect(route('admin.revisions.show', $revision))
            ->assertSessionHas('global', '修訂已接受並套用');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Approved->value,
        ]);

        $this->assertDatabaseHas('revision_reviews', [
            'revision_id' => $revision->id,
            'actor_user_id' => $reviewer->id,
            'action' => RevisionReviewAction::Approved->value,
        ]);

        $vertices = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($vertexType) {
                return $builder
                    ->matchNode('v', $vertexType->age_label_name)
                    ->return('v');
            })
            ->get();

        $this->assertCount(1, $vertices);
    }

    public function test_approve_applies_vertex_property_and_edge_actions(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $schema = $this->setupFullSchema();
        $artistId = $this->createAgeVertex($schema['artist']->age_label_name);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex', 'vertex_type_label' => $schema['track']->age_label_name],
            ['action' => 'create_vertex_property', 'target_ref_order' => 0, 'age_property_name' => 'title', 'value' => 'Glitch Star'],
            ['action' => 'create_edge', 'edge_type_label' => $schema['performs']->age_label_name, 'start_vertex_age_id' => $artistId, 'end_vertex_ref_order' => 0],
            ['action' => 'create_edge_property', 'target_ref_order' => 2, 'age_property_name' => 'track_order', 'value' => '1'],
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.approve', $revision))
            ->assertRedirect(route('admin.revisions.show', $revision));

        $track = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($schema) {
                return $builder
                    ->matchNode('v', $schema['track']->age_label_name)
                    ->where('v.title', '=', 'Glitch Star')
                    ->return('v');
            })
            ->first();

        $this->assertNotNull($track);

        $edge = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($schema) {
                return $builder
                    ->matchNode('s')
                    ->withMatchEdge(\Danny50610\LaravelApacheAgeDriver\Enums\Direction::RIGHT, 'e', $schema['performs']->age_label_name)
                    ->withMatchNode('t', $schema['track']->age_label_name)
                    ->where('e.track_order', '=', 1)
                    ->return('e');
            })
            ->first();

        $this->assertNotNull($edge);
    }

    public function test_approve_fails_when_revision_is_not_pending_review(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'status' => RevisionStatus::Draft,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.approve', $revision))
            ->assertStatus(422);
    }

    public function test_approve_fails_when_validation_fails_on_approval(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();

        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'status' => RevisionStatus::PendingReview,
            'user_id' => $owner->id,
        ]);

        $revision->actions()->create([
            'order' => 0,
            'action' => 'create_vertex',
            'vertex_type_label' => 'missing_vertex_type',
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.approve', $revision))
            ->assertRedirect(route('admin.revisions.show', $revision))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::PendingReview->value,
        ]);
    }

    public function test_approve_fails_when_apply_lock_is_held(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex', 'vertex_type_label' => $vertexType->age_label_name],
        ]);

        $lock = Cache::lock("cohistograph:revision-apply:{$this->graphName}", 30);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($reviewer)
                ->post(route('admin.revisions.approve', $revision))
                ->assertRedirect(route('admin.revisions.show', $revision))
                ->assertSessionHasErrors('lock');
        } finally {
            $lock->release();
        }

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::PendingReview->value,
        ]);
    }

    public function test_reviewer_can_reject_pending_revision_with_comment(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex', 'vertex_type_label' => VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()])->age_label_name],
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.reject', $revision), [
                'comment' => '資料不完整，請補充來源',
            ])
            ->assertRedirect(route('admin.revisions.show', $revision))
            ->assertSessionHas('global', '修訂已退回');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Rejected->value,
        ]);

        $this->assertDatabaseHas('revision_reviews', [
            'revision_id' => $revision->id,
            'actor_user_id' => $reviewer->id,
            'action' => RevisionReviewAction::Rejected->value,
            'comment' => '資料不完整，請補充來源',
        ]);
    }

    public function test_reject_saves_actions_snapshot(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $label = $this->graphLabel();
        VertexType::factory()->createOne(['age_label_name' => $label]);

        $revision = $this->createPendingRevision($owner, [
            ['action' => 'create_vertex', 'vertex_type_label' => $label],
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.reject', $revision), [
                'comment' => '請修正',
            ]);

        $review = RevisionReview::query()
            ->where('revision_id', $revision->id)
            ->firstOrFail();

        $this->assertIsArray($review->actions_snapshot);
        $this->assertSame('create_vertex', $review->actions_snapshot[0]['action']);
        $this->assertSame($label, $review->actions_snapshot[0]['vertex_type_label']);
    }

    public function test_reject_requires_comment(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'status' => RevisionStatus::PendingReview,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.reject', $revision), [
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::PendingReview->value,
        ]);
    }

    public function test_reject_works_even_when_revision_is_invalid(): void
    {
        $owner = User::factory()->createOne();
        $reviewer = $this->createReviewer();

        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'status' => RevisionStatus::PendingReview,
            'user_id' => $owner->id,
        ]);

        $revision->actions()->create([
            'order' => 0,
            'action' => 'create_vertex',
            'vertex_type_label' => 'missing_vertex_type',
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.revisions.reject', $revision), [
                'comment' => '內容有誤',
            ])
            ->assertRedirect(route('admin.revisions.show', $revision));

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'status' => RevisionStatus::Rejected->value,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     */
    private function createPendingRevision(User $owner, array $actions): Revision
    {
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $owner->id,
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

        $this->actingAs($owner)
            ->post(route('revisions.submit', $revision))
            ->assertRedirect(route('revisions.show', $revision));

        return $revision->fresh(['actions']);
    }

    private function createReviewer(): User
    {
        $reviewer = User::factory()->createOne();
        $reviewer->givePermission('revision.review');

        return $reviewer;
    }

    /**
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

    private function createAgeVertex(string $label): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label) {
                return $builder->createNode('v', $label)->return('v');
            })
            ->first();

        return (int) $result->v->id;
    }

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
