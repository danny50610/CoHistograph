<?php

namespace App\Enums;

enum RevisionActionType: string
{
    case CreateVertex = 'create_vertex';
    case DeleteVertex = 'delete_vertex';
    case CreateEdge = 'create_edge';
    case DeleteEdge = 'delete_edge';
    case CreateVertexProperty = 'create_vertex_property';
    case UpdateVertexProperty = 'update_vertex_property';
    case DeleteVertexProperty = 'delete_vertex_property';
    case CreateEdgeProperty = 'create_edge_property';
    case UpdateEdgeProperty = 'update_edge_property';
    case DeleteEdgeProperty = 'delete_edge_property';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
