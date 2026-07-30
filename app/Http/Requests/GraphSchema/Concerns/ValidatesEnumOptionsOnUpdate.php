<?php

namespace App\Http\Requests\GraphSchema\Concerns;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\AgePropertyDataChecker;
use App\Support\EnumOptions;
use Illuminate\Validation\Validator;

trait ValidatesEnumOptionsOnUpdate
{
    use ValidatesEnumOptions;

    /**
     * @return array<string, mixed>
     */
    protected function enumOptionsUpdateRules(bool $typeLocked, PropertyType $currentType): array
    {
        $incomingType = $this->input('age_property_type', $currentType->value);

        if ($typeLocked) {
            $rules = [
                'age_property_type' => ['required', 'string', 'in:'.$currentType->value],
            ];

            if ($currentType === PropertyType::Enum) {
                return array_merge($rules, [
                    'enum_options' => ['required', 'array', 'min:1'],
                    'enum_options.*.value' => ['required', 'string', 'max:'.EnumOptions::VALUE_MAX_LENGTH, 'regex:'.EnumOptions::VALUE_PATTERN],
                    'enum_options.*.label' => ['required', 'string', 'max:'.EnumOptions::LABEL_MAX_LENGTH],
                    'enum_options.*.active' => ['sometimes', 'boolean'],
                    'min_selections' => ['required', 'integer', 'min:'.EnumOptions::SELECTION_COUNT_MIN, 'max:'.EnumOptions::SELECTION_COUNT_MAX],
                    'max_selections' => ['nullable', 'integer', 'min:'.EnumOptions::SELECTION_COUNT_MIN, 'max:'.EnumOptions::SELECTION_COUNT_MAX],
                ]);
            }

            return array_merge($rules, [
                'enum_options' => ['prohibited'],
                'min_selections' => ['prohibited'],
                'max_selections' => ['prohibited'],
            ]);
        }

        if ($incomingType === PropertyType::Enum->value) {
            return $this->enumOptionsRules();
        }

        return [
            'enum_options' => ['prohibited'],
            'min_selections' => ['prohibited'],
            'max_selections' => ['prohibited'],
        ];
    }

    /**
     * @param  callable(): (VertexProperty|EdgeProperty)  $propertyResolver
     * @param  callable(string): bool  $valueInUse
     */
    protected function withEnumOptionsUpdateValidator(
        Validator $validator,
        callable $propertyResolver,
        callable $valueInUse,
        bool $typeLocked,
    ): void {
        $this->withEnumOptionsValidator($validator);

        $validator->after(function (Validator $validator) use ($propertyResolver, $valueInUse, $typeLocked): void {
            /** @var VertexProperty|EdgeProperty $property */
            $property = $propertyResolver();
            $incomingType = $this->input('age_property_type', $property->age_property_type->value);

            if ($typeLocked && $incomingType !== $property->age_property_type->value) {
                $validator->errors()->add('age_property_type', '圖資料庫中已有此屬性的資料，無法變更 Property Type。');

                return;
            }

            if ($incomingType !== PropertyType::Enum->value) {
                return;
            }

            try {
                $newOptions = EnumOptions::normalize(
                    is_array($this->input('enum_options')) ? $this->input('enum_options') : null,
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('enum_options', $e->getMessage());

                return;
            }

            $oldValues = EnumOptions::values(
                EnumOptions::normalize(is_array($property->enum_options) ? $property->enum_options : null),
            );
            $newValues = EnumOptions::values($newOptions);
            $removedValues = array_values(array_diff($oldValues, $newValues));

            foreach ($removedValues as $removedValue) {
                if ($valueInUse($removedValue)) {
                    $validator->errors()->add(
                        'enum_options',
                        "選項「{$removedValue}」仍被圖資料使用，無法刪除；請改為停用。",
                    );
                }
            }

            // Renaming a persisted value is treated as remove+add; block if old value is in use.
            foreach ($oldValues as $oldValue) {
                if (in_array($oldValue, $newValues, true)) {
                    continue;
                }

                // Already reported above via removedValues.
            }
        });
    }

    /**
     * @return list<array{value: string, label: string, active: bool}>|null
     */
    public function enumOptionsForStorage(): ?array
    {
        $type = $this->input('age_property_type');

        if ($type !== PropertyType::Enum->value) {
            return null;
        }

        return EnumOptions::normalize(
            is_array($this->input('enum_options')) ? $this->input('enum_options') : [],
        );
    }

    protected function resolveVertexEnumValueInUseChecker(
        VertexType $vertexType,
        VertexProperty $vertexProperty,
    ): callable {
        $checker = app(AgePropertyDataChecker::class);

        return static fn (string $value): bool => $checker->vertexPropertyEnumValueInUse(
            $vertexType,
            $vertexProperty,
            $value,
        );
    }

    protected function resolveEdgeEnumValueInUseChecker(
        EdgeType $edgeType,
        EdgeProperty $edgeProperty,
    ): callable {
        $checker = app(AgePropertyDataChecker::class);

        return static fn (string $value): bool => $checker->edgePropertyEnumValueInUse(
            $edgeType,
            $edgeProperty,
            $value,
        );
    }
}
