<?php

namespace App\Support;

use App\Models\EdgeProperty;
use App\Models\VertexProperty;
use Illuminate\Support\Collection;

class LocalizedPropertyGrouper
{
    /**
     * @param  iterable<VertexProperty|EdgeProperty>  $propertyDefinitions
     * @param  array<string, mixed>  $propertyValues
     * @return list<array{
     *     title: string,
     *     is_localized: bool,
     *     members: list<array{
     *         property: VertexProperty|EdgeProperty,
     *         locale_label: string|null,
     *         value: mixed,
     *     }>,
     * }>
     */
    public function group(iterable $propertyDefinitions, array $propertyValues = []): array
    {
        /** @var Collection<int, VertexProperty|EdgeProperty> $definitions */
        $definitions = collect($propertyDefinitions)->sortBy('id')->values();

        /** @var array<string, array{title: string, is_localized: bool, sort_id: int, members: list<array{property: VertexProperty|EdgeProperty, locale_label: string|null, value: mixed, locale: string|null}>}> $groups */
        $groups = [];

        foreach ($definitions as $property) {
            $groupKey = $this->groupKey($property);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'title' => $property->name,
                    'is_localized' => $property->locale !== null,
                    'sort_id' => $property->id,
                    'members' => [],
                ];
            }

            $groups[$groupKey]['members'][] = [
                'property' => $property,
                'locale_label' => $this->localeLabel($property->locale),
                'value' => $propertyValues[$property->age_property_name] ?? null,
                'locale' => $property->locale,
            ];
        }

        $localeOrder = array_keys(config('cohistograph.app.graph.locales', []));

        $result = collect($groups)
            ->sortBy('sort_id')
            ->map(function (array $group) use ($localeOrder) {
                if ($group['is_localized']) {
                    $group['members'] = collect($group['members'])
                        ->sortBy(fn (array $member) => $this->localeSortIndex($member['locale'], $localeOrder))
                        ->values()
                        ->map(fn (array $member) => [
                            'property' => $member['property'],
                            'locale_label' => $member['locale_label'],
                            'value' => $member['value'],
                        ])
                        ->all();
                } else {
                    $group['members'] = collect($group['members'])
                        ->map(fn (array $member) => [
                            'property' => $member['property'],
                            'locale_label' => $member['locale_label'],
                            'value' => $member['value'],
                        ])
                        ->all();
                }

                unset($group['sort_id']);

                return $group;
            })
            ->filter(fn (array $group) => $group['members'] !== [])
            ->values()
            ->all();

        return $result;
    }

    private function groupKey(VertexProperty|EdgeProperty $property): string
    {
        if ($property->locale === null) {
            return 'non_localized:'.$property->age_property_name;
        }

        return 'localized:'.LocalizedPropertyName::baseName($property);
    }

    private function localeLabel(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        return config('cohistograph.app.graph.locales')[$locale] ?? $locale;
    }

    /**
     * @param  list<string>  $localeOrder
     */
    private function localeSortIndex(?string $locale, array $localeOrder): int
    {
        if ($locale === null) {
            return PHP_INT_MAX;
        }

        $index = array_search($locale, $localeOrder, true);

        return $index === false ? PHP_INT_MAX : $index;
    }
}
