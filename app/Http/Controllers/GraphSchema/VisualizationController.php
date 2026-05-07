<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;

class VisualizationController extends Controller
{
    public function index()
    {
        $vertexTypeList = VertexType::with('properties')->orderBy('id')->get()->map(fn (VertexType $vt) => [
            'id' => $vt->id,
            'name' => $vt->name,
            'age_label_name' => $vt->age_label_name,
            'description' => $vt->description,
            'properties' => $vt->properties->map(fn (VertexProperty $p) => [
                'name' => $p->name,
                'age_property_name' => $p->age_property_name,
                'age_property_type' => $p->age_property_type,
            ]),
            'url' => route('graph-schema.vertex-type.show', $vt),
        ]);

        $edgeTypeList = EdgeType::with(['properties', 'startVertex', 'endVertex'])->orderBy('id')->get()->map(fn (EdgeType $et) => [
            'id' => $et->id,
            'name' => $et->name,
            'reverse_name' => $et->reverse_name,
            'age_label_name' => $et->age_label_name,
            'description' => $et->description,
            'start_vertex_id' => $et->start_vertex_id,
            'end_vertex_id' => $et->end_vertex_id,
            'start_vertex_name' => $et->startVertex?->name,
            'end_vertex_name' => $et->endVertex?->name,
            'properties' => $et->properties->map(fn (EdgeProperty $p) => [
                'name' => $p->name,
                'age_property_name' => $p->age_property_name,
                'age_property_type' => $p->age_property_type,
            ]),
            'url' => route('graph-schema.edge-type.show', $et),
        ]);

        return view('graph-schema.visualization', compact('vertexTypeList', 'edgeTypeList'));
    }
}
