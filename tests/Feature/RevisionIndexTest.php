<?php

namespace Tests\Feature;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RevisionIndexTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_from_revision_index(): void
    {
        $this->get(route('revisions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_only_own_revisions_and_status_filter_buttons(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();

        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Own Draft Revision',
            'status' => RevisionStatus::Draft,
        ]);
        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Own Pending Revision',
            'status' => RevisionStatus::PendingReview,
        ]);
        Revision::factory()->create([
            'user_id' => $other->id,
            'title' => 'Someone Else Draft',
            'status' => RevisionStatus::Draft,
        ]);

        $this->actingAs($user)
            ->get(route('revisions.index'))
            ->assertOk()
            ->assertSee('我的修訂', false)
            ->assertSee('Own Draft Revision', false)
            ->assertSee('Own Pending Revision', false)
            ->assertDontSee('Someone Else Draft', false)
            ->assertSee('全部', false)
            ->assertSee('草稿', false)
            ->assertSee('待審核', false)
            ->assertSee('已接受', false)
            ->assertSee('已退回', false)
            ->assertSee(route('revisions.index'), false)
            ->assertSee(route('revisions.index', ['status' => RevisionStatus::Draft->value]), false)
            ->assertSee(route('revisions.index', ['status' => RevisionStatus::PendingReview->value]), false)
            ->assertSee(route('revisions.index', ['status' => RevisionStatus::Approved->value]), false)
            ->assertSee(route('revisions.index', ['status' => RevisionStatus::Rejected->value]), false);
    }

    public function test_user_can_filter_revisions_by_status(): void
    {
        $user = User::factory()->createOne();

        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Filter Draft Title',
            'status' => RevisionStatus::Draft,
        ]);
        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Filter Pending Title',
            'status' => RevisionStatus::PendingReview,
        ]);
        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Filter Approved Title',
            'status' => RevisionStatus::Approved,
        ]);
        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Filter Rejected Title',
            'status' => RevisionStatus::Rejected,
        ]);

        $this->actingAs($user)
            ->get(route('revisions.index', ['status' => RevisionStatus::Draft->value]))
            ->assertOk()
            ->assertSee('Filter Draft Title', false)
            ->assertDontSee('Filter Pending Title', false)
            ->assertDontSee('Filter Approved Title', false)
            ->assertDontSee('Filter Rejected Title', false)
            ->assertSee('btn btn-outline-primary active', false);

        $this->actingAs($user)
            ->get(route('revisions.index', ['status' => RevisionStatus::PendingReview->value]))
            ->assertOk()
            ->assertSee('Filter Pending Title', false)
            ->assertDontSee('Filter Draft Title', false)
            ->assertDontSee('Filter Approved Title', false)
            ->assertDontSee('Filter Rejected Title', false);

        $this->actingAs($user)
            ->get(route('revisions.index', ['status' => RevisionStatus::Approved->value]))
            ->assertOk()
            ->assertSee('Filter Approved Title', false)
            ->assertDontSee('Filter Draft Title', false);

        $this->actingAs($user)
            ->get(route('revisions.index', ['status' => RevisionStatus::Rejected->value]))
            ->assertOk()
            ->assertSee('Filter Rejected Title', false)
            ->assertDontSee('Filter Draft Title', false);
    }

    public function test_status_filter_empty_state_uses_status_label(): void
    {
        $user = User::factory()->createOne();

        Revision::factory()->create([
            'user_id' => $user->id,
            'title' => 'Only Draft Exists',
            'status' => RevisionStatus::Draft,
        ]);

        $this->actingAs($user)
            ->get(route('revisions.index', ['status' => RevisionStatus::PendingReview->value]))
            ->assertOk()
            ->assertDontSee('Only Draft Exists', false)
            ->assertSee('目前沒有「待審核」狀態的修訂', false);
    }

    public function test_unknown_status_filter_returns_not_found(): void
    {
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->get(route('revisions.index', ['status' => 'not-a-status']))
            ->assertNotFound();
    }

    public function test_pagination_preserves_status_query_string(): void
    {
        $user = User::factory()->createOne();

        Revision::factory()->count(11)->create([
            'user_id' => $user->id,
            'status' => RevisionStatus::Draft,
        ]);

        $html = $this->actingAs($user)
            ->get(route('revisions.index', ['status' => RevisionStatus::Draft->value]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('status=draft', $html);
        $this->assertStringContainsString('page=2', $html);
    }
}
