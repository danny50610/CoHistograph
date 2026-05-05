<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\EdgeType;
use App\Models\VertexType;

class VisualizationController extends Controller
{
    public function index()
    {
        $vertexTypeList = VertexType::orderBy('id')->get()->map(fn ($vt) => [
            'id' => $vt->id,
            'name' => $vt->name,
            'url' => route('graph-schema.vertex-type.show', $vt),
        ]);

        $edgeTypeList = EdgeType::orderBy('id')->get()->map(fn ($et) => [
            'id' => $et->id,
            'name' => $et->name,
            'start_vertex_id' => $et->start_vertex_id,
            'end_vertex_id' => $et->end_vertex_id,
            'url' => route('graph-schema.edge-type.show', $et),
        ]);

        return view('graph-schema.visualization', compact('vertexTypeList', 'edgeTypeList'));
    }
}

