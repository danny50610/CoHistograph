<?php

namespace Tests\Feature\Mcp;

use App\Enums\RevisionStatus;
use App\Mcp\Servers\CoHistographServer;
use App\Mcp\Tools\Revision\AddRevisionActionTool;
use App\Mcp\Tools\Revision\CreateRevisionTool;
use App\Mcp\Tools\Revision\DeleteRevisionActionTool;
use App\Mcp\Tools\Revision\DeleteRevisionTool;
use App\Mcp\Tools\Revision\GetRevisionTool;
use App\Mcp\Tools\Revision\MoveRevisionActionTool;
use App\Mcp\Tools\Revision\ReopenRevisionTool;
use App\Mcp\Tools\Revision\SearchRevisionsTool;
use App\Mcp\Tools\Revision\SubmitRevisionTool;
use App\Mcp\Tools\Revision\UpdateRevisionActionTool;
use App\Mcp\Tools\Revision\UpdateRevisionTool;
use App\Mcp\Tools\Revision\ValidateRevisionTool;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class RevisionToolsTest extends TestCase
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

    public function test_unauthenticated_tool_call_is_rejected(): void
    {
        $response = CoHistographServer::tool(CreateRevisionTool::class, [
            'title' => '未登入測試',
        ]);

        $response->assertHasErrors(['未授權']);
    }

    public function test_create_update_action_crud_validate_and_submit_flow(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        $create = CoHistographServer::actingAs($user)->tool(CreateRevisionTool::class, [
            'title' => '新增頂點草稿',
            'description' => 'MCP 測試',
        ]);
        $create->assertOk();
        $create->assertStructuredContent(function (AssertableJson $json) {

            $json->where('revision.status', 'draft')
                ->where('actions', [])
                ->etc();

            return true;
        });

        $revisionId = Revision::query()->where('user_id', $user->id)->latest('id')->firstOrFail()->id;

        CoHistographServer::actingAs($user)->tool(UpdateRevisionTool::class, [
            'revision_id' => $revisionId,
            'title' => '更新後標題',
            'description' => '更新後說明',
        ])->assertOk()->assertSee('更新後標題');

        $add = CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revisionId,
            'order' => 0,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ]);
        $add->assertOk();
        $add->assertStructuredContent(function (AssertableJson $json) use ($vertexType) {

            $json->where('actions.0.order', 0)
                ->where('actions.0.action', 'create_vertex')
                ->where('actions.0.vertex_type_label', $vertexType->age_label_name)
                ->etc();

            return true;
        });

        $actionId = Revision::query()->findOrFail($revisionId)->actions()->firstOrFail()->id;

        CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revisionId,
            'order' => 1,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ])->assertOk();

        $secondActionId = Revision::query()->findOrFail($revisionId)->actions()->orderByDesc('order')->firstOrFail()->id;

        CoHistographServer::actingAs($user)->tool(MoveRevisionActionTool::class, [
            'revision_id' => $revisionId,
            'action_id' => $secondActionId,
            'direction' => 'up',
        ])->assertOk();

        $moved = Revision::query()->with(['actions' => fn ($q) => $q->orderBy('order')])->findOrFail($revisionId);
        $this->assertSame($secondActionId, $moved->actions[0]->id);
        $this->assertSame(0, $moved->actions[0]->order);
        $this->assertSame(1, $moved->actions[1]->order);

        CoHistographServer::actingAs($user)->tool(UpdateRevisionActionTool::class, [
            'revision_id' => $revisionId,
            'action_id' => $actionId,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ])->assertOk();

        CoHistographServer::actingAs($user)->tool(DeleteRevisionActionTool::class, [
            'revision_id' => $revisionId,
            'action_id' => $actionId,
        ])->assertOk();

        $this->assertSame(1, Revision::query()->findOrFail($revisionId)->actions()->count());
        $this->assertSame(0, Revision::query()->findOrFail($revisionId)->actions()->firstOrFail()->order);

        CoHistographServer::actingAs($user)->tool(ValidateRevisionTool::class, [
            'revision_id' => $revisionId,
        ])->assertOk()->assertSee('檢查通過');

        $submit = CoHistographServer::actingAs($user)->tool(SubmitRevisionTool::class, [
            'revision_id' => $revisionId,
        ]);
        $submit->assertOk();
        $submit->assertStructuredContent(function (AssertableJson $json) {

            $json->where('submitted', true)->where('revision.status', 'pending_review')->etc();

            return true;
        });

        $this->assertDatabaseHas('revisions', [
            'id' => $revisionId,
            'status' => RevisionStatus::PendingReview->value,
        ]);
    }

    public function test_non_owner_cannot_update_revision(): void
    {
        $owner = User::factory()->createOne();
        $other = User::factory()->createOne();
        $revision = Revision::factory()->createOne([
            'user_id' => $owner->id,
            'status' => RevisionStatus::Draft,
            'title' => '他人草稿',
        ]);

        $response = CoHistographServer::actingAs($other)->tool(UpdateRevisionTool::class, [
            'revision_id' => $revision->id,
            'title' => '嘗試竄改',
        ]);

        $response->assertHasErrors(['無權限更新此修訂']);
    }

    public function test_search_revisions_scopes_to_owner(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();

        Revision::factory()->createOne([
            'user_id' => $user->id,
            'title' => '我的草稿甲',
            'status' => RevisionStatus::Draft,
        ]);
        Revision::factory()->createOne([
            'user_id' => $other->id,
            'title' => '別人的草稿',
            'status' => RevisionStatus::Draft,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchRevisionsTool::class, [
            'query' => '草稿',
        ]);

        $response->assertOk();
        $response->assertSee('我的草稿甲');
        $response->assertDontSee('別人的草稿');
    }

    public function test_reopen_only_allows_rejected_and_delete_only_allows_draft(): void
    {
        $user = User::factory()->createOne();

        $rejected = Revision::factory()->createOne([
            'user_id' => $user->id,
            'status' => RevisionStatus::Rejected,
            'title' => '已退回',
        ]);
        $pending = Revision::factory()->createOne([
            'user_id' => $user->id,
            'status' => RevisionStatus::PendingReview,
            'title' => '審核中',
        ]);
        $draft = Revision::factory()->createOne([
            'user_id' => $user->id,
            'status' => RevisionStatus::Draft,
            'title' => '可刪除草稿',
        ]);

        CoHistographServer::actingAs($user)->tool(ReopenRevisionTool::class, [
            'revision_id' => $rejected->id,
        ])->assertOk()->assertSee('draft');

        CoHistographServer::actingAs($user)->tool(ReopenRevisionTool::class, [
            'revision_id' => $pending->id,
        ])->assertHasErrors(['只有已退回的修訂可以重新開啟']);

        CoHistographServer::actingAs($user)->tool(DeleteRevisionTool::class, [
            'revision_id' => $pending->id,
        ])->assertHasErrors(['只有草稿狀態的修訂可以刪除']);

        CoHistographServer::actingAs($user)->tool(DeleteRevisionTool::class, [
            'revision_id' => $draft->id,
        ])->assertOk()->assertSee('true');

        $this->assertDatabaseMissing('revisions', ['id' => $draft->id]);
    }

    public function test_get_revision_returns_actions_and_validation_cache(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        $create = CoHistographServer::actingAs($user)->tool(CreateRevisionTool::class, [
            'title' => '查詢用修訂',
        ]);
        $create->assertOk();

        $revision = Revision::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

        CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 0,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ])->assertOk();

        $response = CoHistographServer::actingAs($user)->tool(GetRevisionTool::class, [
            'revision_id' => $revision->id,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($revision, $vertexType) {

            $json->where('revision.id', $revision->id)
                ->where('actions.0.vertex_type_label', $vertexType->age_label_name)
                ->where('validation.is_valid', true)
                ->etc();

            return true;
        });
    }

    public function test_move_action_to_order_and_ref_order_invalid_after_reorder(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => $this->graphLabel()]);

        $revision = Revision::factory()->createOne([
            'user_id' => $user->id,
            'status' => RevisionStatus::Draft,
            'title' => '排序測試',
        ]);

        CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 0,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ])->assertOk();

        CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 1,
            'action' => [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => 'missing_prop',
                'value' => 'x',
            ],
        ])->assertOk();

        $createVertexActionId = $revision->actions()->where('order', 0)->firstOrFail()->id;

        $move = CoHistographServer::actingAs($user)->tool(MoveRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'action_id' => $createVertexActionId,
            'to_order' => 1,
        ]);
        $move->assertOk();
        $move->assertStructuredContent(function (AssertableJson $json) {

            $json->where('validation.is_valid', false)->etc();

            return true;
        });
    }

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
