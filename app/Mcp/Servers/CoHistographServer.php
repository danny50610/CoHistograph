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
Collaborative historical-event knowledge graph platform. Query graph schema and vertex data, and help create and submit revisions.

Important rules:
- All graph data changes must go through the Revision workflow; never write directly to Apache AGE.
- VertexType / EdgeType description fields explain each type; look up the schema before creating a revision.
- AGE naming: age_label_name / age_property_name use lowercase letters, digits, and underscores.
- target_age_id: graphid of an existing vertex or edge in AGE.
- target_ref_order: 0-based order of an earlier create_vertex / create_edge in the same Revision.
- create_vertex creates an empty vertex only; set properties with create_vertex_property / update_vertex_property.
- Edit actions with single-action CRUD and move-revision-action; do not overwrite the full actions list.
- Confirm validate-revision passes before submitting.
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
