<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEdgePropertyRequest extends FormRequest
{
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
        /** @var EdgeProperty $edgeProperty */
        $edgeProperty = $this->route('edge_property');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('edge_properties')->where(function ($query) use ($edgeType) {
                    return $query->where('edge_type_id', $edgeType->id);
                })->ignore($edgeProperty),
            ],
            'description' => ['nullable', 'string'],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ];
    }
}
