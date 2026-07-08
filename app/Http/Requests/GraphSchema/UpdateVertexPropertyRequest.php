<?php

namespace App\Http\Requests\GraphSchema;

use App\Enums\PropertyType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVertexPropertyRequest extends FormRequest
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
        /** @var VertexType $vertexType */
        $vertexType = $this->route('vertex_type');
        /** @var VertexProperty $vertexProperty */
        $vertexProperty = $this->route('vertex_property');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('vertex_properties')->where(function ($query) use ($vertexType) {
                    return $query->where('vertex_type_id', $vertexType->id);
                })->ignore($vertexProperty),
            ],
            'description' => ['nullable', 'string'],
            'age_property_type' => ['required', 'string', Rule::enum(PropertyType::class)],
        ];
    }
}
