<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Requests\GraphSchema\Concerns\ResolvesLocalizedAgePropertyName;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Rules\GraphSchema\AgePropertyName;
use App\Rules\GraphSchema\LocaleMutualExclusion;
use App\Rules\GraphSchema\UniqueResolvedAgePropertyName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEdgePropertyRequest extends FormRequest
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
        /** @var EdgeType $edgeType */
        $edgeType = $this->route('edge_type');
        $locale = $this->input('locale');

        return [
            'locale' => ['nullable', 'string', 'regex:/^[a-z]{2}_[a-z]{2}$/', Rule::in(array_keys(config('cohistograph.app.graph.locales')))],
            'base_age_property_name' => [
                'required_with:locale',
                'string',
                'max:58',
                new AgePropertyName,
                new LocaleMutualExclusion(EdgeProperty::class, 'edge_type_id', $edgeType->id, $locale),
            ],
            'age_property_name' => [
                'required_without:locale',
                'string',
                new AgePropertyName,
                new LocaleMutualExclusion(EdgeProperty::class, 'edge_type_id', $edgeType->id, null),
            ],
            'resolved_age_property_name' => [
                'required',
                'string',
                new AgePropertyName,
                new UniqueResolvedAgePropertyName(EdgeProperty::class, 'edge_type_id', $edgeType->id),
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('edge_properties')->where(function ($query) use ($edgeType) {
                    return $query->where('edge_type_id', $edgeType->id);
                }),
            ],
            'description' => ['nullable', 'string'],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ];
    }
}
