<?php

namespace Tests\Feature;

use App\Enums\RevisionReviewAction;
use App\Enums\RevisionStatus;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\RevisionReview;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRevisionReviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_access_admin_revision_list(): void
    {
        $this->get(route('admin.revisions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_admin_revision_list(): void
    {
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->get(route('admin.revisions.index'))
            ->assertForbidden();
    }

    public function test_reviewer_can_view_all_revisions_on_admin_list(): void
    {
        $reviewer = $this->createReviewer();
        $ownerA = User::factory()->createOne(['name' => 'Alice']);
        $ownerB = User::factory()->createOne(['name' => 'Bob']);

        $revisionA = $this->createRevision($ownerA, 'Alice Revision', RevisionStatus::PendingReview);
        $revisionB = $this->createRevision($ownerB, 'Bob Revision', RevisionStatus::Draft);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index'))
            ->assertOk()
            ->assertSee('修訂審核')
            ->assertSee('Alice Revision')
            ->assertSee('Bob Revision')
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee(route('admin.revisions.show', $revisionA), false)
            ->assertSee(route('admin.revisions.show', $revisionB), false);
    }

    public function test_admin_list_shows_empty_state_when_no_revisions(): void
    {
        Revision::query()->delete();

        $reviewer = $this->createReviewer();

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index'))
            ->assertOk()
            ->assertSee('目前還沒有任何修訂');
    }

    public function test_admin_list_shows_status_filter_buttons(): void
    {
        $reviewer = $this->createReviewer();

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index'))
            ->assertOk()
            ->assertSee('全部', false)
            ->assertSee('草稿', false)
            ->assertSee('待審核', false)
            ->assertSee('已接受', false)
            ->assertSee('已退回', false)
            ->assertSee(route('admin.revisions.index', ['status' => RevisionStatus::Draft->value]), false)
            ->assertSee(route('admin.revisions.index', ['status' => RevisionStatus::PendingReview->value]), false)
            ->assertSee(route('admin.revisions.index', ['status' => RevisionStatus::Approved->value]), false)
            ->assertSee(route('admin.revisions.index', ['status' => RevisionStatus::Rejected->value]), false);
    }

    public function test_reviewer_can_filter_admin_revisions_by_status(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();

        $this->createRevision($owner, 'Filter Draft Title', RevisionStatus::Draft);
        $this->createRevision($owner, 'Filter Pending Title', RevisionStatus::PendingReview);
        $this->createRevision($owner, 'Filter Approved Title', RevisionStatus::Approved);
        $this->createRevision($owner, 'Filter Rejected Title', RevisionStatus::Rejected);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index', ['status' => RevisionStatus::PendingReview->value]))
            ->assertOk()
            ->assertSee('Filter Pending Title', false)
            ->assertDontSee('Filter Draft Title', false)
            ->assertDontSee('Filter Approved Title', false)
            ->assertDontSee('Filter Rejected Title', false)
            ->assertSee('btn btn-outline-primary active', false);
    }

    public function test_admin_status_filter_empty_state_uses_status_label(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();

        $this->createRevision($owner, 'Only Draft Exists', RevisionStatus::Draft);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index', ['status' => RevisionStatus::PendingReview->value]))
            ->assertOk()
            ->assertDontSee('Only Draft Exists', false)
            ->assertSee('目前沒有「待審核」狀態的修訂', false);
    }

    public function test_unknown_admin_status_filter_returns_not_found(): void
    {
        $reviewer = $this->createReviewer();

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index', ['status' => 'not-a-status']))
            ->assertNotFound();
    }

    public function test_admin_pagination_preserves_status_query_string(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();

        foreach (range(1, 11) as $index) {
            $this->createRevision($owner, "Paged Revision {$index}", RevisionStatus::PendingReview);
        }

        $html = $this->actingAs($reviewer)
            ->get(route('admin.revisions.index', ['status' => RevisionStatus::PendingReview->value]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('status=pending_review', $html);
        $this->assertStringContainsString('page=2', $html);
    }

    public function test_admin_list_shows_latest_review_time(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();
        $revision = $this->createRevision($owner, 'Reviewed Revision', RevisionStatus::Rejected);

        $review = RevisionReview::query()->create([
            'revision_id' => $revision->id,
            'actor_user_id' => $reviewer->id,
            'action' => RevisionReviewAction::Rejected,
            'comment' => '資料不完整',
        ]);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.index'))
            ->assertOk()
            ->assertSee('Reviewed Revision')
            ->assertSee('最近一次審核')
            ->assertSee((string) $review->created_at);
    }

    public function test_guest_cannot_access_admin_revision_detail(): void
    {
        $owner = User::factory()->createOne();
        $revision = $this->createRevision($owner, 'Detail Revision', RevisionStatus::PendingReview);

        $this->get(route('admin.revisions.show', $revision))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_admin_revision_detail(): void
    {
        $user = User::factory()->createOne();
        $owner = User::factory()->createOne();
        $revision = $this->createRevision($owner, 'Detail Revision', RevisionStatus::PendingReview);

        $this->actingAs($user)
            ->get(route('admin.revisions.show', $revision))
            ->assertForbidden();
    }

    public function test_reviewer_can_view_admin_revision_detail(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne(['name' => 'Carol']);
        $revision = $this->createRevision($owner, 'Pending Detail', RevisionStatus::PendingReview, withAction: true);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.show', $revision))
            ->assertOk()
            ->assertSee('Pending Detail')
            ->assertSee('Carol')
            ->assertSee('審核摘要')
            ->assertSee('操作清單')
            ->assertSee('審核紀錄')
            ->assertSee('接受並套用')
            ->assertSee('退回')
            ->assertSee('新增 Vertex');
    }

    public function test_admin_detail_disables_approve_when_validation_fails(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();
        $revision = $this->createRevision($owner, 'Invalid Revision', RevisionStatus::PendingReview);

        $revision->actions()->create([
            'order' => 0,
            'action' => 'create_vertex',
            'vertex_type_label' => 'missing_vertex_type',
        ]);

        $response = $this->actingAs($reviewer)
            ->get(route('admin.revisions.show', $revision));

        $response->assertOk()
            ->assertSee('驗證未通過')
            ->assertSee('無法接受此修訂');

        $this->assertStringContainsString('disabled', (string) $response->getContent());
    }

    public function test_admin_detail_does_not_show_review_actions_for_approved_revision(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();
        $revision = $this->createRevision($owner, 'Approved Revision', RevisionStatus::Approved);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.show', $revision))
            ->assertOk()
            ->assertDontSee('接受並套用')
            ->assertDontSee('rejectRevisionModal', false);
    }

    public function test_admin_detail_skips_live_revalidation_for_approved_delete_edge_revision(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();
        $revision = $this->createRevision($owner, 'Approved Delete Edge', RevisionStatus::Approved);

        $revision->actions()->create([
            'order' => 0,
            'action' => 'delete_edge',
            'target_age_id' => 999999999,
        ]);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.show', $revision))
            ->assertOk()
            ->assertSee('驗證通過')
            ->assertSee('審核通過時已驗證')
            ->assertDontSee('驗證未通過')
            ->assertDontSee('目標 Edge 不存在')
            ->assertDontSee('進入頁面時重新驗證');
    }

    public function test_admin_detail_shows_create_edge_vertex_display_names(): void
    {
        $reviewer = $this->createReviewer();
        $owner = User::factory()->createOne();

        $personType = VertexType::factory()->create([
            'name' => '人物',
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($personType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $eventType = VertexType::factory()->create([
            'name' => '事件',
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

        $startId = $this->createAgeVertex($personType->age_label_name, ['name' => '白居易']);
        $endId = $this->createAgeVertex($eventType->age_label_name, ['title' => '曲江宴會']);

        $revision = $this->createRevision($owner, 'Edge Display Names', RevisionStatus::PendingReview);
        $revision->actions()->create([
            'order' => 0,
            'action' => 'create_edge',
            'edge_type_label' => $edgeType->age_label_name,
            'start_vertex_age_id' => (string) $startId,
            'end_vertex_age_id' => (string) $endId,
        ]);

        $this->actingAs($reviewer)
            ->get(route('admin.revisions.show', $revision))
            ->assertOk()
            ->assertSee('新增 Edge：白居易 - '.$edgeType->age_label_name.' - 曲江宴會')
            ->assertDontSee('ID:'.$startId)
            ->assertDontSee('ID:'.$endId);
    }

    private function createReviewer(): User
    {
        $reviewer = User::factory()->createOne();
        $reviewer->givePermission('revision.review');

        return $reviewer;
    }

    private function createRevision(
        User $owner,
        string $title,
        RevisionStatus $status,
        bool $withAction = false,
    ): Revision {
        $revision = Revision::query()->create([
            'title' => $title,
            'description' => fake()->sentence(),
            'status' => $status,
            'user_id' => $owner->id,
        ]);

        if ($withAction) {
            $revision->actions()->create([
                'order' => 0,
                'action' => 'create_vertex',
                'vertex_type_label' => 'person',
            ]);
        }

        return $revision;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createAgeVertex(string $label, array $properties = []): int
    {
        $connection = (string) config('cohistograph.app.graph.connection-name');
        $graphName = (string) config('cohistograph.app.graph.name');

        $result = DB::connection($connection)
            ->apacheAgeCypher($graphName, function (Builder $builder) use ($label, $properties) {
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
