<?php

namespace App\Support;

use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Support\Collection;

class ShowPropertyNamePresenter
{
    public function __construct(
        private LocalizedPropertyGrouper $grouper,
    ) {}

    /**
     * @param  Collection<int, VertexProperty>  $properties
     * @return list<array{value: string, label: string}>
     */
    public function options(Collection $properties): array
    {
        $displayLocale = (string) config('cohistograph.app.graph.display_locale');
        $options = [];

        foreach ($this->grouper->group($properties) as $group) {
            if (! $group['is_localized']) {
                $property = $group['members'][0]['property'];

                $options[] = [
                    'value' => $property->age_property_name,
                    'label' => sprintf('%s (%s)', $property->name, $property->age_property_name),
                ];

                continue;
            }

            $baseName = LocalizedPropertyName::baseName($group['members'][0]['property']);
            $labelProperty = collect($group['members'])
                ->first(fn (array $member) => $member['property']->locale === $displayLocale)['property']
                ?? $group['members'][0]['property'];

            $options[] = [
                'value' => $baseName,
                'label' => sprintf('%s (%s) — 多語系', $labelProperty->name, $baseName),
            ];
        }

        return $options;
    }

    public function displayLabel(VertexType $vertexType): string
    {
        if ($vertexType->show_property_name === null || $vertexType->show_property_name === '') {
            return '—';
        }

        $showName = $vertexType->show_property_name;
        $properties = $vertexType->properties;

        $exact = $properties->first(
            fn (VertexProperty $property) => $property->age_property_name === $showName,
        );

        if ($exact !== null && $exact->locale === null) {
            return $showName;
        }

        $hasLocalizedGroup = $properties->contains(
            fn (VertexProperty $property) => $property->locale !== null
                && LocalizedPropertyName::baseName($property) === $showName,
        );

        if ($hasLocalizedGroup) {
            $displayLocale = (string) config('cohistograph.app.graph.display_locale');

            return sprintf('%s（多語系，顯示語言：%s）', $showName, $displayLocale);
        }

        return $showName;
    }
}
