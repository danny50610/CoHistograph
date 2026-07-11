<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use App\Http\Requests\GraphSchema\StoreVertexPropertyRequest;
use App\Http\Requests\GraphSchema\UpdateVertexPropertyRequest;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\AgePropertyDataChecker;
use App\Support\LocalizedPropertyName;

class VertexPropertyController extends Controller
{
    public function __construct(
        private AgePropertyDataChecker $agePropertyDataChecker,
    ) {
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
        $agePropertyNameLocked = $this->agePropertyDataChecker->vertexPropertyHasData($vertexType, $vertexProperty);

        return view('graph-schema.vertex-property.create-or-edit', compact('vertexType', 'vertexProperty', 'agePropertyNameLocked'));
    }

    public function update(UpdateVertexPropertyRequest $request, VertexType $vertexType, VertexProperty $vertexProperty)
    {
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_type' => $validated['age_property_type'],
        ];

        if (! $request->agePropertyNameLocked()) {
            $oldAgePropertyName = $vertexProperty->age_property_name;
            $oldBaseName = LocalizedPropertyName::baseName($vertexProperty);

            $attributes['age_property_name'] = $validated['resolved_age_property_name'];
            $vertexProperty->update($attributes);
            $this->syncShowPropertyName($vertexType, $vertexProperty, $oldAgePropertyName, $oldBaseName);
        } else {
            $vertexProperty->update($attributes);
        }

        return redirect()->route('graph-schema.vertex-property.show', [$vertexType, $vertexProperty])
            ->with('global', "Vertex Property「{$vertexProperty->name}」更新完成");
    }

    public function destroy(VertexType $vertexType, VertexProperty $vertexProperty)
    {
        if ($this->agePropertyDataChecker->vertexPropertyHasData($vertexType, $vertexProperty)) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有 Vertex 使用「{$vertexProperty->name}」屬性");
        }

        $vertexProperty->delete();

        return redirect()->route('graph-schema.vertex-type.show', [$vertexType])
            ->with('global', "Vertex Property「{$vertexProperty->name}」刪除完成");
    }

    private function syncShowPropertyName(
        VertexType $vertexType,
        VertexProperty $vertexProperty,
        string $oldAgePropertyName,
        string $oldBaseName,
    ): void {
        $showPropertyName = $vertexType->show_property_name;

        if ($showPropertyName === null || $showPropertyName === '') {
            return;
        }

        if ($showPropertyName === $oldAgePropertyName) {
            $vertexType->update(['show_property_name' => $vertexProperty->age_property_name]);

            return;
        }

        if ($showPropertyName === $oldBaseName) {
            $vertexType->update(['show_property_name' => LocalizedPropertyName::baseName($vertexProperty)]);
        }
    }
}
