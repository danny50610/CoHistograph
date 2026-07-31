<?php

namespace App\Mcp\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;

trait ProvidesRevisionActionSchema
{
    protected function revisionActionSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'action' => $schema->string()
                ->description('One of the ten actions, e.g. create_vertex, create_edge')
                ->required(),
            'target_age_id' => $schema->integer()
                ->description('AGE graphid of the target (mutually exclusive with target_ref_order)'),
            'target_ref_order' => $schema->integer()
                ->description('Order of create_vertex / create_edge in the same Revision'),
            'vertex_type_label' => $schema->string()
                ->description('Required AGE label for create_vertex'),
            'edge_type_label' => $schema->string()
                ->description('Required AGE label for create_edge'),
            'start_vertex_age_id' => $schema->integer()
                ->description('create_edge start vertex AGE ID'),
            'start_vertex_ref_order' => $schema->integer()
                ->description('create_edge start: order of create_vertex in the same Revision'),
            'end_vertex_age_id' => $schema->integer()
                ->description('create_edge end vertex AGE ID'),
            'end_vertex_ref_order' => $schema->integer()
                ->description('create_edge end: order of create_vertex in the same Revision'),
            'age_property_name' => $schema->string()
                ->description('Required for property-related actions'),
            'value' => $schema->string()
                ->description('Property value as a string; type follows the schema property definition'),
        ])->description('Fields for a single RevisionAction');
    }
}
