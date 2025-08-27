<?php

namespace App\Http\Controllers\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgePropertyName;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, VertexType $vertexType)
    {
        $this->validate($request, [
            'name' => ['required', 'string', Rule::unique('vertex_properties')->where(function ($query) use ($vertexType) {
                    return $query->where('vertex_type_id', $vertexType->id);
                })
            ],
            'description' => ['nullable', 'string'],
            'age_property_name' => ['required', 'string', new AgePropertyName()],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ]);

        $vertexProperty = new VertexProperty([
            'name' => $request->input('name'),
            'description' => $request->input('description', ''),
            'age_property_name' => $request->input('age_property_name'),
            'age_property_type' => $request->input('age_property_type'),
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

    public function update(Request $request, VertexType $vertexType, VertexProperty $vertexProperty)
    {
        $this->validate($request, [
            'name' => [
                'required',
                'string',
                Rule::unique('vertex_properties')->where(function ($query) use ($vertexType) {
                    return $query->where('vertex_type_id', $vertexType->id);
                })->ignore($vertexProperty)
            ],
            'description' => ['nullable', 'string'],
            'age_property_name' => ['required', 'string', new AgePropertyName()],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ]);

        // TODO: age_property_name cannot change when exists

        $vertexProperty->update([
            'name' => $request->input('name'),
            'description' => $request->input('description', ''),
            'age_property_name' => $request->input('age_property_name'),
            'age_property_type' => $request->input('age_property_type'),
        ]);

        return redirect()->route('graph-schema.vertex-property.show', [$vertexType, $vertexProperty])
            ->with('global', "Vertex Property「{$vertexProperty->name}」更新完成");
    }

    public function destroy(VertexProperty $vertexProperty)
    {
        throw new \Exception('Not impl.');
    }
}
