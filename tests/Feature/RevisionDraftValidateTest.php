<?php

namespace Tests\Feature;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RevisionDraftValidateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_validate_draft_and_receive_business_rule_errors(): void
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
            ->postJson(route('revisions.validate', $revision), [
                'title' => '即時檢查',
                'description' => '檢查內容',
                'actions' => [],
            ])
            ->assertOk()
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('summary', '檢查未通過，請修正錯誤後再繼續')
            ->assertJsonPath('general_errors.0', '至少需要一筆操作才能提交審核');
    }

    public function test_validate_draft_returns_422_when_request_payload_is_invalid(): void
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
            ->postJson(route('revisions.validate', $revision), [
                'title' => '',
                'description' => '檢查內容',
                'actions' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_validate_draft_with_actions_returns_action_errors_instead_of_server_error(): void
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
            ->postJson(route('revisions.validate', $revision), [
                'title' => '即時檢查',
                'description' => '檢查內容',
                'actions' => [
                    [
                        'action' => 'create_vertex',
                        'vertex_type_label' => 'missing_vertex_type',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('action_errors.0.0.code', 'VERTEX_TYPE_NOT_FOUND');
    }

    public function test_user_cannot_validate_another_users_revision(): void
    {
        /** @var User $owner */
        $owner = User::factory()->createOne();
        /** @var User $otherUser */
        $otherUser = User::factory()->createOne();
        $revision = Revision::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => RevisionStatus::Draft,
            'user_id' => $owner->id,
        ]);

        $this->actAsUser($otherUser)
            ->postJson(route('revisions.validate', $revision), [
                'title' => '即時檢查',
                'description' => '檢查內容',
                'actions' => [],
            ])
            ->assertForbidden();
    }

    private function actAsUser(User $user): static
    {
        /** @var Authenticatable $authenticatable */
        $authenticatable = $user;

        return $this->actingAs($authenticatable);
    }
}
