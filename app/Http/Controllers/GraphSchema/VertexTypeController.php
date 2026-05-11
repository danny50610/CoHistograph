<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgeLabelName;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $vertexTypeList = VertexType::orderBy('id', 'desc')->paginate();

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
            'age_label_name' => ['required', 'string', new AgeLabelName, Rule::unique('vertex_types'), Rule::unique('edge_types')],
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
            'age_label_name' => ['required', 'string', new AgeLabelName, Rule::unique('vertex_types')->ignore($vertexType), Rule::unique('edge_types')],
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
        if ($vertexType->startEdgeTypes()->exists() || $vertexType->endEdgeTypes()->exists()) {
            return redirect()->back()->with('warning', "無法刪除，因為 Vertex「{$vertexType->name}」還有 Edge 類型關聯");
        }

        if ($vertexType->properties()->exists()) {
            return redirect()->back()->with('warning', "無法刪除，因為 Vertex「{$vertexType->name}」還有屬性");
        }

        $hasVertices = DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType) {
                return $builder->matchNode('v', $vertexType->age_label_name)
                    ->return('v')
                    ->limit(1);
            })->get()->isNotEmpty();

        if ($hasVertices) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有「{$vertexType->name}」類型的 Vertex 資料");
        }

        $vertexType->delete();

        return redirect()->route('graph-schema.vertex-type.index')
            ->with('global', "Vertex「{$vertexType->name}」刪除完成");
    }
}
