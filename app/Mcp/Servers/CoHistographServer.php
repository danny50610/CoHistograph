<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Graph\GetEdgeDetailTool;
use App\Mcp\Tools\Graph\GetVertexDetailTool;
use App\Mcp\Tools\Graph\ListVertexNeighborsTool;
use App\Mcp\Tools\Graph\SearchEdgesTool;
use App\Mcp\Tools\Graph\SearchVerticesTool;
use App\Mcp\Tools\Revision\AddRevisionActionTool;
use App\Mcp\Tools\Revision\CreateRevisionTool;
use App\Mcp\Tools\Revision\DeleteRevisionActionTool;
use App\Mcp\Tools\Revision\DeleteRevisionTool;
use App\Mcp\Tools\Revision\GetRevisionTool;
use App\Mcp\Tools\Revision\ListRevisionActionsTool;
use App\Mcp\Tools\Revision\MoveRevisionActionTool;
use App\Mcp\Tools\Revision\ReopenRevisionTool;
use App\Mcp\Tools\Revision\SearchRevisionsTool;
use App\Mcp\Tools\Revision\SubmitRevisionTool;
use App\Mcp\Tools\Revision\UpdateRevisionActionTool;
use App\Mcp\Tools\Revision\UpdateRevisionTool;
use App\Mcp\Tools\Revision\ValidateRevisionTool;
use App\Mcp\Tools\Schema\SearchEdgeTypesTool;
use App\Mcp\Tools\Schema\SearchVertexTypesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('CoHistograph')]
#[Version('1.0.0')]
#[Instructions(<<<'INSTRUCTIONS'
協作式歷史事件知識圖譜平台。可查詢圖譜 Schema 與頂點資料、協助建立與提交修訂。

重要規則：
- 所有圖資料變更必須透過 Revision 工作流，不可直接寫入 Apache AGE。
- VertexType / EdgeType 定義「是什麼」；usage_guidelines 說明「應該怎麼用」。
- AGE 命名規則：age_label_name / age_property_name 使用小寫英數字與底線。
- target_age_id：指向 AGE 中既有頂點或邊的 graphid。
- target_ref_order：引用同份 Revision 內較早的 create_vertex / create_edge 的 order（0-based）。
- create_vertex 只建立空頂點；屬性請用 create_vertex_property / update_vertex_property。
- Action 編輯請使用單筆 CRUD 與 move-revision-action，不要整份覆寫 actions。
- 提交前請確認 validate-revision 通過。
INSTRUCTIONS)]
class CoHistographServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        SearchVerticesTool::class,
        GetVertexDetailTool::class,
        SearchEdgesTool::class,
        GetEdgeDetailTool::class,
        ListVertexNeighborsTool::class,
        SearchVertexTypesTool::class,
        SearchEdgeTypesTool::class,
        SearchRevisionsTool::class,
        CreateRevisionTool::class,
        UpdateRevisionTool::class,
        ListRevisionActionsTool::class,
        AddRevisionActionTool::class,
        UpdateRevisionActionTool::class,
        DeleteRevisionActionTool::class,
        MoveRevisionActionTool::class,
        ValidateRevisionTool::class,
        ReopenRevisionTool::class,
        DeleteRevisionTool::class,
        SubmitRevisionTool::class,
        GetRevisionTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];
}
