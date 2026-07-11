<?php

namespace App\Rules\GraphSchema;

use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\LocalizedPropertyName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;

class ValidShowPropertyName implements ValidationRule
{
    /**
     * @param  Collection<int, VertexProperty>  $properties
     */
    public function __construct(
        private Collection $properties,
    ) {}

    public static function forVertexType(VertexType $vertexType): self
    {
        return new self($vertexType->properties);
    }

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('顯示 property 格式不正確');

            return;
        }

        $exact = $this->properties->first(
            fn (VertexProperty $property) => $property->age_property_name === $value,
        );

        if ($exact !== null) {
            return;
        }

        $hasLocalizedGroup = $this->properties->contains(
            fn (VertexProperty $property) => $property->locale !== null
                && LocalizedPropertyName::baseName($property) === $value,
        );

        if (! $hasLocalizedGroup) {
            $fail('顯示 property 必須對應到存在的屬性或多語系屬性群組');
        }
    }
}
