<?php

namespace App\Support;

use App\Models\VertexProperty;
use Illuminate\Support\Collection;

class VertexDisplayNameResolver
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  Collection<int, VertexProperty>  $propertyDefinitions
     */
    public function resolve(
        ?string $showPropertyName,
        array $properties,
        Collection $propertyDefinitions,
        ?string $locale = null,
    ): string {
        if ($showPropertyName === null || $showPropertyName === '') {
            return '';
        }

        $exact = $propertyDefinitions->first(
            fn (VertexProperty $property) => $property->age_property_name === $showPropertyName,
        );

        if ($exact !== null) {
            return $this->stringValue($properties[$showPropertyName] ?? null);
        }

        $hasLocalizedMembers = $propertyDefinitions->contains(
            fn (VertexProperty $property) => $property->locale !== null
                && LocalizedPropertyName::agePropertyNameForLocale($showPropertyName, $property->locale) === $property->age_property_name,
        );

        if (! $hasLocalizedMembers) {
            return '';
        }

        foreach ($this->resolveLocaleOrder($locale) as $tryLocale) {
            $key = LocalizedPropertyName::agePropertyNameForLocale($showPropertyName, $tryLocale);

            if (array_key_exists($key, $properties) && $this->isNonEmpty($properties[$key])) {
                return $this->stringValue($properties[$key]);
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function resolveLocaleOrder(?string $locale): array
    {
        $primary = $locale ?? (string) config('cohistograph.app.graph.display_locale');
        $fallback = config('cohistograph.app.graph.display_locale_fallback', []);

        return array_values(array_unique(array_filter([$primary, ...$fallback])));
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function isNonEmpty(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
