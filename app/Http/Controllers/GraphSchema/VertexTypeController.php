<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\VertexType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VertexTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:graph-schema.manage');
    }

    public function index()
    {
        $vertexTypeList = VertexType::orderBy('id')->paginate();

        return view('graph-schema.vertex-type.index', compact('vertexTypeList'));
    }

    public function show(VertexType $vertexType)
    {
        $vertexType->load('properties');

        return view('graph-schema.vertex-type.show', compact('vertexType'));
    }

    public function create()
    {
        return view('graph-schema.vertex-type.create-or-edit');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('vertex_types'), Rule::unique('edge_types')],
            'age_label_name' => ['required', 'string', Rule::unique('vertex_types'), Rule::unique('edge_types')],
            'description' => ['string'],
        ]);

        $vertexType = VertexType::create([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex「{$vertexType->name}」建立完成");
    }

    public function edit(VertexType $vertexType)
    {
        return view('graph-schema.vertex-type.create-or-edit', compact('vertexType'));
    }

    public function update(Request $request, VertexType $vertexType)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('vertex_types')->ignore($vertexType), Rule::unique('edge_types')],
            'age_label_name' => ['required', 'string', Rule::unique('vertex_types')->ignore($vertexType), Rule::unique('edge_types')],
            'description' => ['string'],
        ]);

        // TODO: age_label_name cannot change when exists

        $vertexType->update([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex「{$vertexType->name}」更新完成");
    }

    public function destroy(VertexType $vertexType)
    {
        throw new \Exception('Not impl.');
    }
}
