<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphSchema\StoreVertexPropertyRequest;
use App\Http\Requests\GraphSchema\UpdateVertexPropertyRequest;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Support\Facades\DB;

class VertexPropertyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:graph-schema.manage');
    }

    public function show(VertexType $vertexType, VertexProperty $vertexProperty)
    {
        return view('graph-schema.vertex-property.show', compact('vertexType', 'vertexProperty'));
    }

    public function create(VertexType $vertexType)
    {
        return view('graph-schema.vertex-property.create-or-edit', compact('vertexType'));
    }

    public function store(StoreVertexPropertyRequest $request, VertexType $vertexType)
    {
        $validated = $request->validated();

        $vertexProperty = new VertexProperty([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_name' => $validated['resolved_age_property_name'],
            'age_property_type' => $validated['age_property_type'],
            'locale' => $validated['locale'],
        ]);
        $vertexProperty->vertexType()->associate($vertexType);
        $vertexProperty->save();

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex Property「{$vertexProperty->name}」建立完成");
    }

    public function edit(VertexType $vertexType, VertexProperty $vertexProperty)
    {
        return view('graph-schema.vertex-property.create-or-edit', compact('vertexType', 'vertexProperty'));
    }

    public function update(UpdateVertexPropertyRequest $request, VertexType $vertexType, VertexProperty $vertexProperty)
    {
        $validated = $request->validated();

        $vertexProperty->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_type' => $validated['age_property_type'],
        ]);

        return redirect()->route('graph-schema.vertex-property.show', [$vertexType, $vertexProperty])
            ->with('global', "Vertex Property「{$vertexProperty->name}」更新完成");
    }

    public function destroy(VertexType $vertexType, VertexProperty $vertexProperty)
    {
        // age_label_name and age_property_name are validated to [a-z0-9_] only,
        // so embedding them directly in the Cypher query is safe.
        // Cypher does not support parameterized label/property names.
        $hasData = DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType, $vertexProperty) {
                return $builder->matchRaw('(v:'.$vertexType->age_label_name.') WHERE v.'.$vertexProperty->age_property_name.' IS NOT NULL')
                    ->return('v')
                    ->limit(1);
            })->get()->isNotEmpty();

        if ($hasData) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有 Vertex 使用「{$vertexProperty->name}」屬性");
        }

        $vertexProperty->delete();

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex Property「{$vertexProperty->name}」刪除完成");
    }
}
