<?php

namespace App\Http\Controllers\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GraphSchema\StoreEdgePropertyRequest;
use App\Http\Requests\GraphSchema\UpdateEdgePropertyRequest;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Support\AgePropertyDataChecker;
use App\Support\EnumOptions;

class EdgePropertyController extends Controller
{
    public function __construct(
        private AgePropertyDataChecker $agePropertyDataChecker,
    ) {
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
        $isEnum = ($validated['age_property_type'] ?? null) === PropertyType::Enum->value;

        $edgeProperty = new EdgeProperty([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_name' => $validated['resolved_age_property_name'],
            'age_property_type' => $validated['age_property_type'],
            'locale' => $isEnum ? null : ($validated['locale'] ?? null),
            'enum_options' => $request->enumOptionsForStorage(),
            ...$request->enumSelectionLimitsForStorage(),
        ]);
        $edgeProperty->edgeType()->associate($edgeType);
        $edgeProperty->save();

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge Property「{$edgeProperty->name}」建立完成");
    }

    public function edit(EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        $agePropertyNameLocked = $this->agePropertyDataChecker->edgePropertyHasData($edgeType, $edgeProperty);
        $usedEnumValues = [];

        if ($edgeProperty->age_property_type === PropertyType::Enum) {
            $usedEnumValues = $this->agePropertyDataChecker->usedEdgeEnumValues(
                $edgeType,
                $edgeProperty,
                EnumOptions::values(EnumOptions::normalize($edgeProperty->enum_options)),
            );
        }

        return view('graph-schema.edge-property.create-or-edit', compact(
            'edgeType',
            'edgeProperty',
            'agePropertyNameLocked',
            'usedEnumValues',
        ));
    }

    public function update(UpdateEdgePropertyRequest $request, EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        $validated = $request->validated();
        $type = PropertyType::from((string) ($validated['age_property_type'] ?? $edgeProperty->age_property_type->value));

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'age_property_type' => $type,
            'enum_options' => $type === PropertyType::Enum ? $request->enumOptionsForStorage() : null,
            ...$request->enumSelectionLimitsForStorage(),
        ];

        if ($type === PropertyType::Enum) {
            $attributes['locale'] = null;
        }

        if (! $request->agePropertyNameLocked()) {
            $attributes['age_property_name'] = $validated['resolved_age_property_name'];
        }

        $edgeProperty->update($attributes);

        return redirect()->route('graph-schema.edge-property.show', [$edgeType, $edgeProperty])
            ->with('global', "Edge Property「{$edgeProperty->name}」更新完成");
    }

    public function destroy(EdgeType $edgeType, EdgeProperty $edgeProperty)
    {
        if ($this->agePropertyDataChecker->edgePropertyHasData($edgeType, $edgeProperty)) {
            return redirect()->back()->with('warning', "無法刪除，因為圖資料庫中還有 Edge 使用「{$edgeProperty->name}」屬性");
        }

        $edgeProperty->delete();

        return redirect()->route('graph-schema.edge-type.show', [$edgeType])
            ->with('global', "Edge Property「{$edgeProperty->name}」刪除完成");
    }
}
