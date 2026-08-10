<?php

namespace App\Mcp\Concerns;

use App\Enums\RevisionActionType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;

trait ProvidesRevisionActionSchema
{
    protected function revisionActionSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'action' => $schema->string()
                ->description('One of: create_vertex, delete_vertex, create_edge, delete_edge, create_vertex_property, update_vertex_property, delete_vertex_property, create_edge_property, update_edge_property, delete_edge_property')
                ->enum(RevisionActionType::class)
                ->required(),
            'target_age_id' => $schema->integer()
                ->description('AGE graphid of the target vertex/edge. Mutually exclusive with target_ref_order. Used by delete_* and *_property actions.'),
            'target_ref_order' => $schema->integer()
                ->description('0-based order of create_vertex / create_edge in the same revision. Mutually exclusive with target_age_id.'),
            'vertex_type_label' => $schema->string()
                ->description('Required for create_vertex: VertexType age_label_name from search-vertex-types.'),
            'edge_type_label' => $schema->string()
                ->description('Required for create_edge: EdgeType age_label_name from search-edge-types.'),
            'start_vertex_age_id' => $schema->integer()
                ->description('create_edge start vertex AGE ID. Mutually exclusive with start_vertex_ref_order.'),
            'start_vertex_ref_order' => $schema->integer()
                ->description('create_edge start: order of create_vertex in the same revision. Mutually exclusive with start_vertex_age_id.'),
            'end_vertex_age_id' => $schema->integer()
                ->description('create_edge end vertex AGE ID. Mutually exclusive with end_vertex_ref_order.'),
            'end_vertex_ref_order' => $schema->integer()
                ->description('create_edge end: order of create_vertex in the same revision. Mutually exclusive with end_vertex_age_id.'),
            'age_property_name' => $schema->string()
                ->description('Required for *_property actions: property age_property_name from schema search tools.'),
            'value' => $schema->union(['array', 'string', 'integer', 'number', 'boolean'])
                ->description('Required for create/update *_property. JSON type follows age_property_type from schema search: ENUM → JSON array of option value strings from enum_options, e.g. ["rock","jazz"] (never a scalar string); STRING/DATE/MONTH_DAY/TIMESTAMPTZ → string; INTEGER → integer; FLOAT → number; BOOLEAN → boolean. For ENUM respect min_selections/max_selections and prefer active options. Empty array is invalid — use delete_*_property to clear.'),
        ])->description('Single RevisionAction fields. Include only fields required for the chosen action type.');
    }
}
