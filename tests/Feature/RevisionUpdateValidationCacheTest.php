<?php

namespace Tests\Feature;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RevisionUpdateValidationCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_persists_validation_snapshot(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $this->actAsUser($user)
            ->put(route('revisions.update', $revision), [
                'title' => '儲存後驗證快照',
                'description' => '儲存時要落地驗證結果',
                'actions' => [],
            ])
            ->assertRedirect(route('revisions.show', $revision));

        $revision->refresh();

        $this->assertFalse((bool) $revision->last_validation_is_valid);
        $this->assertSame('檢查未通過，請修正錯誤後再繼續', $revision->last_validation_summary);
        $this->assertSame(['至少需要一筆操作才能提交審核'], $revision->last_validation_general_errors);
        $this->assertSame([], $revision->last_validation_action_errors);
        $this->assertNotNull($revision->last_validated_at);
    }

    public function test_show_page_displays_cached_validation_result_after_save(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $this->actAsUser($user)
            ->put(route('revisions.update', $revision), [
                'title' => '顯示快照結果',
                'description' => 'show 頁應顯示儲存後檢查結果',
                'actions' => [],
            ])
            ->assertRedirect(route('revisions.show', $revision));

        $this->actAsUser($user)
            ->get(route('revisions.show', $revision))
            ->assertOk()
            ->assertSee('檢查未通過，請修正錯誤後再繼續')
            ->assertSee('至少需要一筆操作才能提交審核')
            ->assertSee('最近檢查時間：');
    }

    private function actAsUser(User $user): static
    {
        return $this->actingAs($user);
    }
}
