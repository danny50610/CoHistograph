<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgeLabelName;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VertexTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:graph-schema.manage')
            ->only([
                'create',
                'store',
                'edit',
                'update',
                'destroy',
            ]);
    }

    public function index()
    {
        $vertexTypeList = VertexType::orderBy('id')->paginate();

        return view('graph-schema.vertex-type.index', compact('vertexTypeList'));
    }

    public function show(VertexType $vertexType)
    {
        $vertexType->load([
            'properties',
            'startEdgeTypes',
            'startEdgeTypes.endVertex',
            'endEdgeTypes',
            'endEdgeTypes.startVertex',
        ]);

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
            'age_label_name' => ['required', 'string', new AgeLabelName(), Rule::unique('vertex_types'), Rule::unique('edge_types')],
            'description' => ['nullable', 'string'],
        ]);

        $vertexType = VertexType::create([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description') ?? '',
        ]);

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex「{$vertexType->name}」建立完成");
    }

    public function edit(VertexType $vertexType)
    {
        $propertyOptions = $vertexType->properties
            ->map(fn ($item) => [
                'value' => $item->age_property_name,
                'label' => $item->name,
            ])
            ->toArray();

        return view('graph-schema.vertex-type.create-or-edit', compact('vertexType', 'propertyOptions'));
    }

    public function update(Request $request, VertexType $vertexType)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('vertex_types')->ignore($vertexType), Rule::unique('edge_types')],
            'age_label_name' => ['required', 'string', new AgeLabelName(), Rule::unique('vertex_types')->ignore($vertexType), Rule::unique('edge_types')],
            'description' => ['nullable', 'string'],
            'show_property_name' => ['nullable', 'string', Rule::in($vertexType->properties->pluck('age_property_name')->toArray())],
        ]);

        // TODO: age_label_name cannot change when exists

        $vertexType->update([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description') ?? '',
            'show_property_name' => $request->input('show_property_name', null),
        ]);

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex「{$vertexType->name}」更新完成");
    }

    public function destroy(VertexType $vertexType)
    {
        // TODO: Implement the destroy method
        throw new \Exception('Not impl.');
    }
}
