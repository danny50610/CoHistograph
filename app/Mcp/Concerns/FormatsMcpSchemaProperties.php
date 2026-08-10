<?php

namespace App\Mcp\Concerns;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\VertexProperty;

trait FormatsMcpSchemaProperties
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     age_property_name: string,
     *     age_property_type: string,
     *     description: string|null,
     *     locale: string|null,
     *     enum_options: list<array{value: string, label: string, active: bool}>|null,
     *     min_selections: int|null,
     *     max_selections: int|null
     * }
     */
    protected function formatSchemaProperty(VertexProperty|EdgeProperty $property): array
    {
        $isEnum = $property->age_property_type === PropertyType::Enum;

        return [
            'id' => $property->id,
            'name' => $property->name,
            'age_property_name' => $property->age_property_name,
            'age_property_type' => $property->age_property_type->value,
            'description' => $property->description,
            'locale' => $property->locale,
            'enum_options' => $isEnum ? $property->enum_options : null,
            'min_selections' => $isEnum ? $property->min_selections : null,
            'max_selections' => $isEnum ? $property->max_selections : null,
        ];
    }
}
