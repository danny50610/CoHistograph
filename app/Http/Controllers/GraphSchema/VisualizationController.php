<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\EdgeType;
use App\Models\VertexType;

class VisualizationController extends Controller
{
    public function index()
    {
        $vertexTypeList = VertexType::orderBy('id')->get();
        $edgeTypeList = EdgeType::orderBy('id')->get();

        return view('graph-schema.visualization', compact('vertexTypeList', 'edgeTypeList'));
    }
}

