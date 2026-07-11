<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgePropertyName;
use App\Rules\GraphSchema\LocaleMutualExclusion;
use App\Rules\GraphSchema\UniqueResolvedAgePropertyName;
use App\Support\AgePropertyDataChecker;
use App\Support\LocalizedPropertyName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVertexPropertyRequest extends FormRequest
{
    private bool $agePropertyNameLocked = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var VertexType $vertexType */
        $vertexType = $this->route('vertex_type');
        /** @var VertexProperty $vertexProperty */
        $vertexProperty = $this->route('vertex_property');

        $this->agePropertyNameLocked = app(AgePropertyDataChecker::class)
            ->vertexPropertyHasData($vertexType, $vertexProperty);

        if ($this->agePropertyNameLocked) {
            return;
        }

        $locale = $vertexProperty->locale;

        if ($locale !== null) {
            if (! $this->filled('base_age_property_name')) {
                $this->merge([
                    'base_age_property_name' => LocalizedPropertyName::baseName($vertexProperty),
                ]);
            }
        } elseif (! $this->filled('age_property_name')) {
            $this->merge([
                'age_property_name' => $vertexProperty->age_property_name,
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
        /** @var VertexType $vertexType */
        $vertexType = $this->route('vertex_type');
        /** @var VertexProperty $vertexProperty */
        $vertexProperty = $this->route('vertex_property');

        $rules = [
            'name' => [
                'required',
                'string',
                Rule::unique('vertex_properties')->where(function ($query) use ($vertexType) {
                    return $query->where('vertex_type_id', $vertexType->id);
                })->ignore($vertexProperty),
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

        $locale = $vertexProperty->locale;

        if ($locale !== null) {
            $rules['base_age_property_name'] = [
                'required',
                'string',
                'max:58',
                new AgePropertyName,
                new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, $locale, $vertexProperty->id),
            ];
            $rules['age_property_name'] = ['prohibited'];
        } else {
            $rules['age_property_name'] = [
                'required',
                'string',
                new AgePropertyName,
                new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, null, $vertexProperty->id),
            ];
            $rules['base_age_property_name'] = ['prohibited'];
        }

        $rules['resolved_age_property_name'] = [
            'required',
            'string',
            new AgePropertyName,
            new UniqueResolvedAgePropertyName(VertexProperty::class, 'vertex_type_id', $vertexType->id, $vertexProperty->id),
        ];

        return $rules;
    }

    public function agePropertyNameLocked(): bool
    {
        return $this->agePropertyNameLocked;
    }
}
