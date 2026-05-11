<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Models\EdgeType;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgeLabelName;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $edgeTypeList = EdgeType::orderBy('id', 'desc')->paginate();

        return view('graph-schema.edge-type.index', compact('edgeTypeList'));
    }

    public function show(EdgeType $edgeType)
    {
        $edgeType->load('properties');

        return view('graph-schema.edge-type.show', compact('edgeType'));
    }

    protected function getVertexOptions()
    {
        return VertexType::orderBy('id')
            ->select(['id', 'name'])
            ->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->name,
            ])
            ->toArray();
    }

    public function create()
    {
        $vertexOptions = $this->getVertexOptions();

        return view('graph-schema.edge-type.create-or-edit', compact('vertexOptions'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('vertex_types')],
            'reverse_name' => ['nullable', 'string'],
            'age_label_name' => ['required', 'string', new AgeLabelName, Rule::unique('vertex_types')],
            'description' => ['nullable', 'string'],
            'start_vertex_id' => ['required', 'exists:vertex_types,id'],
            'end_vertex_id' => ['required', 'exists:vertex_types,id'],
        ]);

        $edgeType = EdgeType::create([
            'name' => $request->input('name'),
            'reverse_name' => $request->input('reverse_name') ?? '',
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description') ?? '',
            'start_vertex_id' => $request->input('start_vertex_id'),
            'end_vertex_id' => $request->input('end_vertex_id'),
        ]);

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge {$edgeType->name}」建立完成");
    }

    public function edit(EdgeType $edgeType)
    {
        $vertexOptions = $this->getVertexOptions();

        return view('graph-schema.edge-type.create-or-edit', compact('edgeType', 'vertexOptions'));
    }

    public function update(Request $request, EdgeType $edgeType)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('vertex_types')],
            'reverse_name' => ['nullable', 'string'],
            'age_label_name' => ['required', 'string', new AgeLabelName, Rule::unique('vertex_types')],
            'description' => ['nullable', 'string'],
            'start_vertex_id' => ['required', 'exists:vertex_types,id'],
            'end_vertex_id' => ['required', 'exists:vertex_types,id'],
        ]);

        // TODO: age_label_name cannot change when exists

        $edgeType->update([
            'name' => $request->input('name'),
            'reverse_name' => $request->input('reverse_name') ?? '',
            'age_label_name' => $request->input('age_label_name'),
            'description' => $request->input('description') ?? '',
            'start_vertex_id' => $request->input('start_vertex_id'),
            'end_vertex_id' => $request->input('end_vertex_id'),
        ]);

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge {$edgeType->name}」更新完成");
    }

    public function destroy(EdgeType $edgeType)
    {
        if ($edgeType->properties()->exists()) {
            return redirect()->back()->with('warning', "無法刪除，因為 Edge「{$edgeType->name}」還有屬性");
        }

        $hasEdges = DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType) {
                return $builder->matchNode()
                    ->withMatchEdge(Direction::BOTH, 'e', $edgeType->age_label_name)
                    ->withMatchNode()
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();

        if ($hasEdges) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有「{$edgeType->name}」類型的 Edge 資料");
        }

        $edgeType->delete();

        return redirect()->route('graph-schema.edge-type.index')
            ->with('global', "Edge「{$edgeType->name}」刪除完成");
    }
}
