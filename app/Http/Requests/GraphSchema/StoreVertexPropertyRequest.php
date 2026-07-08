<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Requests\GraphSchema\Concerns\ResolvesLocalizedAgePropertyName;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Rules\GraphSchema\AgePropertyName;
use App\Rules\GraphSchema\LocaleMutualExclusion;
use App\Rules\GraphSchema\UniqueResolvedAgePropertyName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVertexPropertyRequest extends FormRequest
{
    use ResolvesLocalizedAgePropertyName;

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

        return [
            'locale' => ['nullable', 'string', 'regex:/^[a-z]{2}_[a-z]{2}$/', Rule::in(array_keys(config('cohistograph.app.graph.locales')))],
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
    }
}
