<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DraftRevisionPrompt;
use App\Mcp\Prompts\ExploreVertexContextPrompt;
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
Collaborative historical-event knowledge graph. Query schema and graph data; help create and submit revisions. Never write Apache AGE directly — all graph changes go through the Revision workflow.

Recommended workflow:
1. search-vertex-types / search-edge-types with include_properties=true to learn age_label_name, age_property_name, age_property_type, and (for ENUM) enum_options / min_selections / max_selections.
2. search-vertices / search-edges / get-*-detail / list-vertex-neighbors to obtain existing target_age_id values.
3. search-revisions (status=draft|rejected) to continue work, or create-revision for a new draft.
4. Edit with add/update/delete/move-revision-action one action at a time — never overwrite the full actions list. Each of those tools re-validates and returns validation in the response — check validation.is_valid (do not assume you must call validate-revision after every edit).
5. Optional: validate-revision to re-check without changing content (e.g. after reopen-revision, or when you only have a stale validation snapshot).
6. When validation.is_valid is true, call submit-revision. submit-revision re-validates; if invalid it returns submitted=false plus validation errors (it does not require a prior validate-revision call when the latest edit response was already valid).
7. If rejected: reopen-revision before editing again.

Domain rules:
- AGE naming (age_label_name / age_property_name): lowercase letters, digits, underscores.
- target_age_id: graphid of an existing vertex or edge in AGE.
- target_ref_order: 0-based order of an earlier create_vertex / create_edge in the SAME revision (mutually exclusive with the matching *_age_id).
- create_vertex creates an empty vertex only; set properties with create_vertex_property / update_vertex_property.
- Property value JSON type follows age_property_type: STRING/DATE/MONTH_DAY/TIMESTAMPTZ → string; INTEGER → integer; FLOAT → number; BOOLEAN → boolean; ENUM → JSON array of option value strings from enum_options, e.g. ["rock","jazz"] (never a scalar string like "rock"; respect min_selections/max_selections; prefer active options). Empty array is invalid — clear with delete_*_property.
- Only draft revisions can be edited or submitted.
- Revisions created or edited via MCP are marked is_ai_assisted=true (sticky).

RevisionAction.action values and fields (omit unrelated fields):
- create_vertex: vertex_type_label
- delete_vertex: target_age_id XOR target_ref_order
- create_edge: edge_type_label; start via start_vertex_age_id XOR start_vertex_ref_order; end via end_vertex_age_id XOR end_vertex_ref_order
- delete_edge: target_age_id XOR target_ref_order
- create_vertex_property / update_vertex_property: target_age_id XOR target_ref_order; age_property_name; value
- delete_vertex_property: target_age_id XOR target_ref_order; age_property_name
- create_edge_property / update_edge_property: target_age_id XOR target_ref_order; age_property_name; value
- delete_edge_property: target_age_id XOR target_ref_order; age_property_name

delete/move/add-revision-action remap *_ref_order onto the same referenced actions after resequencing. Deleting a referenced action clears those refs (validation will fail until fixed). Moving a create_* after a dependent action still fails REFERENCE_NOT_PREVIOUS.
validation.action_warnings (DUPLICATE_EDGE / DUPLICATE_VERTEX) are soft notices only — they do not set is_valid=false and do not block submit-revision.
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
    protected array $prompts = [
        ExploreVertexContextPrompt::class,
        DraftRevisionPrompt::class,
    ];
}
