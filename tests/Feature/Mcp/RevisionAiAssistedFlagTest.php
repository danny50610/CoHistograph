<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CoHistographServer;
use App\Mcp\Tools\Revision\CreateRevisionTool;
use App\Mcp\Tools\Revision\UpdateRevisionTool;
use App\Models\Revision;
use App\Models\User;
use App\Services\RevisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class RevisionAiAssistedFlagTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mcp_create_revision_sets_is_ai_assisted(): void
    {
        $user = User::factory()->createOne();

        CoHistographServer::actingAs($user)->tool(CreateRevisionTool::class, [
            'title' => 'AI 建立的草稿',
            'description' => 'via MCP',
        ])->assertOk()->assertStructuredContent(function (AssertableJson $json) {
            $json->where('revision.is_ai_assisted', true)->etc();

            return true;
        });

        $this->assertTrue(
            Revision::query()->where('user_id', $user->id)->latest('id')->firstOrFail()->is_ai_assisted
        );
    }

    public function test_web_create_revision_leaves_is_ai_assisted_false(): void
    {
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->post(route('revisions.store'), [
                'title' => '手動建立的草稿',
                'description' => 'via web',
            ])
            ->assertRedirect();

        $revision = Revision::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertFalse($revision->is_ai_assisted);

        $this->actingAs($user)
            ->get(route('revisions.show', $revision))
            ->assertOk()
            ->assertDontSee('>AI</span>', false);
    }

    public function test_mcp_edit_marks_human_created_revision_as_ai_assisted(): void
    {
        $user = User::factory()->createOne();
        $revision = Revision::factory()->createOne([
            'user_id' => $user->id,
            'title' => '人工草稿',
            'is_ai_assisted' => false,
        ]);

        CoHistographServer::actingAs($user)->tool(UpdateRevisionTool::class, [
            'revision_id' => $revision->id,
            'title' => 'AI 改過的標題',
            'description' => 'edited via MCP',
        ])->assertOk()->assertStructuredContent(function (AssertableJson $json) {
            $json->where('revision.is_ai_assisted', true)
                ->where('revision.title', 'AI 改過的標題')
                ->etc();

            return true;
        });

        $this->assertTrue($revision->fresh()->is_ai_assisted);

        $this->actingAs($user)
            ->get(route('revisions.show', $revision))
            ->assertOk()
            ->assertSee('>AI</span>', false);
    }

    public function test_web_update_does_not_set_ai_assisted_flag(): void
    {
        $user = User::factory()->createOne();
        $revision = app(RevisionService::class)->create($user, [
            'title' => '人工草稿',
            'description' => null,
        ]);

        $this->assertFalse($revision->is_ai_assisted);

        $this->actingAs($user)
            ->put(route('revisions.update', $revision), [
                'title' => '人工更新',
                'description' => 'still human',
                'actions' => [],
            ])
            ->assertRedirect();

        $this->assertFalse($revision->fresh()->is_ai_assisted);
    }
}
