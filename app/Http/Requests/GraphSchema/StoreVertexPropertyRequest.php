<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Requests\GraphSchema\Concerns\ResolvesLocalizedAgePropertyName;
use App\Http\Requests\GraphSchema\Concerns\ValidatesEnumOptions;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgePropertyName;
use App\Rules\GraphSchema\LocaleMutualExclusion;
use App\Rules\GraphSchema\UniqueResolvedAgePropertyName;
use App\Support\EnumOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVertexPropertyRequest extends FormRequest
{
    use ResolvesLocalizedAgePropertyName;
    use ValidatesEnumOptions;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var VertexType $vertexType */
        $vertexType = $this->route('vertex_type');
        $locale = $this->input('locale');
        $isEnum = $this->input('age_property_type') === PropertyType::Enum->value;

        $rules = [
            'locale' => $isEnum
                ? ['prohibited']
                : ['nullable', 'string', 'regex:/^[a-z]{2}_[a-z]{2}$/', Rule::in(array_keys(config('cohistograph.app.graph.locales')))],
            'base_age_property_name' => [
                'required_with:locale',
                'string',
                'max:58',
                new AgePropertyName,
                new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, $locale),
            ],
            'age_property_name' => [
                'required_without:locale',
                'string',
                new AgePropertyName,
                new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, null),
            ],
            'resolved_age_property_name' => [
                'required',
                'string',
                new AgePropertyName,
                new UniqueResolvedAgePropertyName(VertexProperty::class, 'vertex_type_id', $vertexType->id),
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('vertex_properties')->where(function ($query) use ($vertexType) {
                    return $query->where('vertex_type_id', $vertexType->id);
                }),
            ],
            'description' => ['nullable', 'string'],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ];

        return array_merge($rules, $this->enumOptionsRules());
    }

    public function withValidator(Validator $validator): void
    {
        $this->withEnumOptionsValidator($validator);
    }

    /**
     * @return list<array{value: string, label: string, active: bool}>|null
     */
    public function enumOptionsForStorage(): ?array
    {
        if ($this->input('age_property_type') !== PropertyType::Enum->value) {
            return null;
        }

        return EnumOptions::normalize(
            is_array($this->input('enum_options')) ? $this->input('enum_options') : [],
        );
    }
}
