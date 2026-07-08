<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphSchema\StoreEdgePropertyRequest;
use App\Http\Requests\GraphSchema\UpdateEdgePropertyRequest;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Support\Facades\DB;

class EdgePropertyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:graph-schema.manage');
    }

    public function show(EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        return view('graph-schema.edge-property.show', compact('edgeType', 'edgeProperty'));
    }

    public function create(EdgeType $edgeType)
    {
        return view('graph-schema.edge-property.create-or-edit', compact('edgeType'));
    }

    public function store(StoreEdgePropertyRequest $request, EdgeType $edgeType)
    {
        $validated = $request->validated();

        $edgeProperty = new EdgeProperty([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_name' => $validated['resolved_age_property_name'],
            'age_property_type' => $validated['age_property_type'],
            'locale' => $validated['locale'],
        ]);
        $edgeProperty->edgeType()->associate($edgeType);
        $edgeProperty->save();

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge Property「{$edgeProperty->name}」建立完成");
    }

    public function edit(EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        return view('graph-schema.edge-property.create-or-edit', compact('edgeType', 'edgeProperty'));
    }

    public function update(UpdateEdgePropertyRequest $request, EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        $validated = $request->validated();

        $edgeProperty->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_type' => $validated['age_property_type'],
        ]);

        return redirect()->route('graph-schema.edge-property.show', [$edgeType, $edgeProperty])
            ->with('global', "Edge Property「{$edgeProperty->name}」更新完成");
    }

    public function destroy(EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        // age_label_name and age_property_name are validated to [a-z0-9_] only,
        // so embedding them directly in the Cypher query is safe.
        // Cypher does not support parameterized label/property names.
        $hasData = DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $edgeProperty) {
                return $builder->matchRaw('()-[e:'.$edgeType->age_label_name.']-() WHERE e.'.$edgeProperty->age_property_name.' IS NOT NULL')
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();

        if ($hasData) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有 Edge 使用「{$edgeProperty->name}」屬性");
        }

        $edgeProperty->delete();

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge Property「{$edgeProperty->name}」刪除完成");
    }
}
