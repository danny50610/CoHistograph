<?php

namespace App\Support;

use App\Enums\PropertyType;
use InvalidArgumentException;

/**
 * Helpers for ENUM property `enum_options` JSON and selected value lists.
 *
 * @phpstan-type EnumOption array{value: string, label: string, active: bool}
 */
class EnumOptions
{
    public const VALUE_PATTERN = '/^[a-z0-9_+-]+$/';

    public const VALUE_MAX_LENGTH = 64;

    public const LABEL_MAX_LENGTH = 128;

    /**
     * @param  array<int, mixed>|null  $options
     * @return list<EnumOption>
     */
    public static function normalize(?array $options): array
    {
        if ($options === null) {
            return [];
        }

        $normalized = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                throw new InvalidArgumentException('Each enum option must be an array.');
            }

            $value = isset($option['value']) && is_string($option['value']) ? trim($option['value']) : '';
            $label = isset($option['label']) && is_string($option['label']) ? trim($option['label']) : '';
            $active = array_key_exists('active', $option)
                ? filter_var($option['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true;

            if ($active === null) {
                $active = (bool) $option['active'];
            }

            $normalized[] = [
                'value' => $value,
                'label' => $label,
                'active' => $active,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<EnumOption>  $options
     * @return list<string>
     */
    public static function values(array $options): array
    {
        return array_map(static fn (array $option): string => $option['value'], $options);
    }

    /**
     * @param  list<EnumOption>  $options
     * @return list<string>
     */
    public static function activeValues(array $options): array
    {
        return array_values(array_map(
            static fn (array $option): string => $option['value'],
            array_filter($options, static fn (array $option): bool => $option['active']),
        ));
    }

    /**
     * @param  list<EnumOption>  $options
     * @return array<string, string>
     */
    public static function valueToLabelMap(array $options): array
    {
        $map = [];

        foreach ($options as $option) {
            $map[$option['value']] = $option['label'];
        }

        return $map;
    }

    /**
     * Sort selected values by enum_options definition order; unknown values keep relative order at the end.
     *
     * @param  list<string>  $selected
     * @param  list<EnumOption>  $options
     * @return list<string>
     */
    public static function sortSelectedByDefinition(array $selected, array $options): array
    {
        $order = array_flip(self::values($options));
        $known = [];
        $unknown = [];

        foreach ($selected as $value) {
            if (array_key_exists($value, $order)) {
                $known[] = $value;
            } else {
                $unknown[] = $value;
            }
        }

        usort($known, static fn (string $a, string $b): int => $order[$a] <=> $order[$b]);

        return array_merge($known, $unknown);
    }

    /**
     * @param  list<string>  $selected
     * @param  list<EnumOption>  $options
     * @param  list<string>  $currentOnGraph
     * @return list<string> validation error messages (empty if ok)
     */
    public static function validateSelected(
        array $selected,
        array $options,
        array $currentOnGraph = [],
        bool $isCreate = false,
    ): array {
        $errors = [];

        if ($selected === []) {
            $errors[] = 'ENUM 屬性值不可為空；若要清空請使用刪除屬性操作。';

            return $errors;
        }

        if (count($selected) !== count(array_unique($selected))) {
            $errors[] = 'ENUM 屬性值不可重複。';
        }

        $defined = self::values($options);
        $active = self::activeValues($options);
        $currentSet = array_values(array_unique($currentOnGraph));
        $inactiveOrOrphanOnGraph = array_values(array_filter(
            $currentSet,
            static fn (string $value): bool => ! in_array($value, $active, true),
        ));

        $allowed = $isCreate
            ? $active
            : array_values(array_unique(array_merge($active, $inactiveOrOrphanOnGraph)));

        foreach ($selected as $value) {
            if ($value === '') {
                $errors[] = 'ENUM 屬性值必須為非空字串。';

                continue;
            }

            if (! in_array($value, $allowed, true)) {
                if (in_array($value, $defined, true)) {
                    $errors[] = "選項「{$value}」已停用，不可新選。";
                } else {
                    $errors[] = "選項「{$value}」不在允許清單中，不可新引入。";
                }
            }
        }

        if ($active === [] && array_diff($selected, $inactiveOrOrphanOnGraph) !== []) {
            $errors[] = '此 ENUM 屬性目前沒有可選的啟用選項。';
        }

        if ($active === [] && $isCreate) {
            $errors[] = '此 ENUM 屬性目前沒有可選的啟用選項。';
        }

        return array_values(array_unique($errors));
    }

    public static function isValidOptionValue(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= self::VALUE_MAX_LENGTH
            && preg_match(self::VALUE_PATTERN, $value) === 1;
    }

    /**
     * @param  list<string>  $values
     * @param  list<EnumOption>  $options
     */
    public static function formatLabels(array $values, array $options, string $separator = '、'): string
    {
        $map = self::valueToLabelMap($options);
        $sorted = self::sortSelectedByDefinition($values, $options);

        $labels = array_map(
            static fn (string $value): string => $map[$value] ?? $value,
            $sorted,
        );

        return implode($separator, $labels);
    }

    public static function requiresEnumOptions(PropertyType $type): bool
    {
        return $type === PropertyType::Enum;
    }
}
