<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\EdgeType;
use App\Rules\GraphSchema\AgeLabelName;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EdgeTypeController extends Controller
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
        $edgeTypeList = EdgeType::orderBy('id')->paginate();

        return view('graph-schema.edge-type.index', compact('edgeTypeList'));
    }

    public function show(EdgeType $edgeType)
    {
        $edgeType->load('properties');

        return view('graph-schema.edge-type.show', compact('edgeType'));
    }

    public function create()
    {
        return view('graph-schema.edge-type.create-or-edit');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('edge_types'), Rule::unique('vertex_types')],
            'age_label_name' => ['required', 'string', new AgeLabelName(), Rule::unique('edge_types'), Rule::unique('vertex_types')],
            'description' => ['nullable', 'string'],
        ]);

        $edgeType = EdgeType::create([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description', ''),
        ]);

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge {$edgeType->name}」建立完成");
    }

    public function edit(EdgeType $edgeType)
    {
        return view('graph-schema.edge-type.create-or-edit', compact('edgeType'));
    }

    public function update(Request $request, EdgeType $edgeType)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('edge_types')->ignore($edgeType)],
            'age_label_name' => ['required', 'string', new AgeLabelName(), Rule::unique('edge_types')->ignore($edgeType)],
            'description' => ['nullable', 'string'],
        ]);

        // TODO: age_label_name cannot change when exists

        $edgeType->update([
            'name' => $request->input('name'),
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description', ''),
        ]);

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge {$edgeType->name}」更新完成");
    }

    public function destroy(EdgeType $edgeType)
    {
        throw new \Exception('Not impl.');
    }
}
