<?php

namespace App\Http\Controllers\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Rules\GraphSchema\AgePropertyName;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, EdgeType $edgeType)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('edge_properties')->where(function ($query) use ($edgeType) {
                return $query->where('edge_type_id', $edgeType->id);
            }),
            ],
            'description' => ['nullable', 'string'],
            'age_property_name' => ['required', 'string', new AgePropertyName],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ]);

        $edgeProperty = new EdgeProperty([
            'name' => $request->input('name'),
            'description' => $request->input('description', ''),
            'age_property_name' => $request->input('age_property_name'),
            'age_property_type' => $request->input('age_property_type'),
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

    public function update(Request $request, EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        $this->validate($request, [
            'name' => [
                'required',
                'string',
                Rule::unique('edge_properties')->where(function ($query) use ($edgeType) {
                    return $query->where('edge_type_id', $edgeType->id);
                })->ignore($edgeProperty),
            ],
            'description' => ['nullable', 'string'],
            'age_property_name' => ['required', 'string', new AgePropertyName],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ]);

        // TODO: age_property_name cannot change when exists

        $edgeProperty->update([
            'name' => $request->input('name'),
            'description' => $request->input('description', ''),
            'age_property_name' => $request->input('age_property_name'),
            'age_property_type' => $request->input('age_property_type'),
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
