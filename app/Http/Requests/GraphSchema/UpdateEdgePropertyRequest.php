<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Rules\GraphSchema\AgePropertyName;
use App\Rules\GraphSchema\LocaleMutualExclusion;
use App\Rules\GraphSchema\UniqueResolvedAgePropertyName;
use App\Support\AgePropertyDataChecker;
use App\Support\LocalizedPropertyName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEdgePropertyRequest extends FormRequest
{
    private bool $agePropertyNameLocked = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var EdgeType $edgeType */
        $edgeType = $this->route('edge_type');
        /** @var EdgeProperty $edgeProperty */
        $edgeProperty = $this->route('edge_property');

        $this->agePropertyNameLocked = app(AgePropertyDataChecker::class)
            ->edgePropertyHasData($edgeType, $edgeProperty);

        if ($this->agePropertyNameLocked) {
            return;
        }

        $locale = $edgeProperty->locale;

        if ($locale !== null) {
            if (! $this->filled('base_age_property_name')) {
                $this->merge([
                    'base_age_property_name' => LocalizedPropertyName::baseName($edgeProperty),
                ]);
            }
        } elseif (! $this->filled('age_property_name')) {
            $this->merge([
                'age_property_name' => $edgeProperty->age_property_name,
            ]);
        }

        $this->merge([
            'resolved_age_property_name' => $locale
                ? $this->input('base_age_property_name').'_'.$locale
                : $this->input('age_property_name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var EdgeType $edgeType */
        $edgeType = $this->route('edge_type');
        /** @var EdgeProperty $edgeProperty */
        $edgeProperty = $this->route('edge_property');

        $rules = [
            'name' => [
                'required',
                'string',
                Rule::unique('edge_properties')->where(function ($query) use ($edgeType) {
                    return $query->where('edge_type_id', $edgeType->id);
                })->ignore($edgeProperty),
            ],
            'description' => ['nullable', 'string'],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
            'locale' => ['prohibited'],
        ];

        if ($this->agePropertyNameLocked) {
            $rules['age_property_name'] = ['prohibited'];
            $rules['base_age_property_name'] = ['prohibited'];
            $rules['resolved_age_property_name'] = ['prohibited'];

            return $rules;
        }

        $locale = $edgeProperty->locale;

        if ($locale !== null) {
            $rules['base_age_property_name'] = [
                'required',
                'string',
                'max:58',
                new AgePropertyName,
                new LocaleMutualExclusion(EdgeProperty::class, 'edge_type_id', $edgeType->id, $locale, $edgeProperty->id),
            ];
            $rules['age_property_name'] = ['prohibited'];
        } else {
            $rules['age_property_name'] = [
                'required',
                'string',
                new AgePropertyName,
                new LocaleMutualExclusion(EdgeProperty::class, 'edge_type_id', $edgeType->id, null, $edgeProperty->id),
            ];
            $rules['base_age_property_name'] = ['prohibited'];
        }

        $rules['resolved_age_property_name'] = [
            'required',
            'string',
            new AgePropertyName,
            new UniqueResolvedAgePropertyName(EdgeProperty::class, 'edge_type_id', $edgeType->id, $edgeProperty->id),
        ];

        return $rules;
    }

    public function agePropertyNameLocked(): bool
    {
        return $this->agePropertyNameLocked;
    }
}
