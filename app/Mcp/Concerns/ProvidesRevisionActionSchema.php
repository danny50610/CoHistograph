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
                ->description('十種 action 之一，如 create_vertex、create_edge')
                ->required(),
            'target_age_id' => $schema->integer()
                ->description('操作對象 AGE graphid（與 target_ref_order 互斥）'),
            'target_ref_order' => $schema->integer()
                ->description('引用同 Revision 內 create_vertex / create_edge 的 order'),
            'vertex_type_label' => $schema->string()
                ->description('create_vertex 時必填的 AGE label'),
            'edge_type_label' => $schema->string()
                ->description('create_edge 時必填的 AGE label'),
            'start_vertex_age_id' => $schema->integer()
                ->description('create_edge 起點 AGE ID'),
            'start_vertex_ref_order' => $schema->integer()
                ->description('create_edge 起點引用同 Revision 內 create_vertex 的 order'),
            'end_vertex_age_id' => $schema->integer()
                ->description('create_edge 終點 AGE ID'),
            'end_vertex_ref_order' => $schema->integer()
                ->description('create_edge 終點引用同 Revision 內 create_vertex 的 order'),
            'age_property_name' => $schema->string()
                ->description('property 相關 action 時必填'),
            'value' => $schema->string()
                ->description('屬性值（字串；型別依 Schema 屬性定義）'),
        ])->description('單筆 RevisionAction 欄位');
    }
}
