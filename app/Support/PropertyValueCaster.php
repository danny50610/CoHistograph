<?php

namespace App\Support;

use App\Enums\PropertyType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class PropertyValueCaster
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const MONTH_DAY_PATTERN = '/^\d{2}-\d{2}$/';

    /**
     * Time of day: `HH:mm` or `HH:mm:ss` (24-hour, zero-padded).
     */
    private const TIME_PATTERN = '/^\d{2}:\d{2}(?::\d{2})?$/';

    /**
     * Leap year used as a sentinel when materializing month-day as Carbon.
     * Allows valid Feb 29 values without attaching a real year.
     */
    private const MONTH_DAY_SENTINEL_YEAR = 2000;

    /**
     * Sentinel date used when materializing time-of-day as Carbon.
     */
    private const TIME_SENTINEL_DATE = '2000-01-01';

    /**
     * ISO-8601 datetime with explicit timezone offset or Z.
     * Examples: 2024-07-22T14:30:00+08:00, 2024-07-22T06:30:00Z
     */
    private const TIMESTAMPTZ_PATTERN = '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|[+-]\d{2}:?\d{2})$/';

    /**
     * Structural type check. ENUM grandfather / option rules are enforced elsewhere.
     */
    public function matchesType(mixed $value, PropertyType $propertyType): bool
    {
        if ($propertyType === PropertyType::Enum) {
            return $this->isEnumListShape($value);
        }

        if (is_bool($value)) {
            return $propertyType === PropertyType::Boolean;
        }

        if (is_int($value)) {
            return $propertyType === PropertyType::Integer || $propertyType === PropertyType::Float;
        }

        if (is_float($value)) {
            return $propertyType === PropertyType::Float;
        }

        if (! is_string($value)) {
            return false;
        }

        // ENUM is handled above; remaining cases are scalar string inputs.
        return match ($propertyType) {
            PropertyType::Integer => preg_match('/^-?\d+$/', $value) === 1,
            PropertyType::Float => preg_match('/^-?(?:\d+|\d*\.\d+)$/', $value) === 1,
            PropertyType::Boolean => in_array(strtolower($value), ['true', 'false'], true),
            PropertyType::String => true,
            PropertyType::Date => $this->isValidDate($value),
            PropertyType::MonthDay => $this->isValidMonthDay($value),
            PropertyType::Time => $this->isValidTime($value),
            PropertyType::Timestamptz => $this->isValidTimestamptz($value),
        };
    }

    /**
     * Convert a revision/input value into the PHP/AGE storage value.
     *
     * DATE / MONTH_DAY / TIME / TIMESTAMPTZ stay as normalized strings in AGE (agtype string).
     * ENUM becomes a sorted list of option value strings when $enumOptions is provided.
     *
     * @param  list<array{value: string, label: string, active: bool}>|null  $enumOptions
     * @return int|float|bool|string|list<string>
     */
    public function toStorage(mixed $value, PropertyType $propertyType, ?array $enumOptions = null): int|float|bool|string|array
    {
        if (! $this->matchesType($value, $propertyType)) {
            $display = is_scalar($value) || $value === null
                ? var_export($value, true)
                : get_debug_type($value);

            throw new InvalidArgumentException("Value [{$display}] does not match property type [{$propertyType->value}].");
        }

        if ($propertyType === PropertyType::Enum) {
            /** @var list<string> $selected */
            $selected = array_values(array_map(
                static fn (mixed $item): string => (string) $item,
                is_array($value) ? $value : [],
            ));

            if ($enumOptions !== null) {
                return EnumOptions::sortSelectedByDefinition($selected, $enumOptions);
            }

            return $selected;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return match ($propertyType) {
                PropertyType::Integer => (int) $value,
                PropertyType::Float => (float) $value,
                PropertyType::Boolean => (bool) $value,
                default => throw new InvalidArgumentException("Unexpected native value for [{$propertyType->value}]."),
            };
        }

        $stringValue = (string) $value;

        return match ($propertyType) {
            PropertyType::Integer => (int) $stringValue,
            PropertyType::Float => (float) $stringValue,
            PropertyType::Boolean => strtolower($stringValue) === 'true',
            PropertyType::String => $stringValue,
            PropertyType::Date => $stringValue,
            PropertyType::MonthDay => $stringValue,
            PropertyType::Time => $this->normalizeTime($stringValue),
            PropertyType::Timestamptz => $this->normalizeTimestamptz($stringValue),
        };
    }

    /**
     * Convert a value read from AGE into the PHP object/scalar for this property type.
     *
     * DATE → CarbonImmutable (date-only, midnight UTC)
     * MONTH_DAY → CarbonImmutable (sentinel year 2000, midnight UTC)
     * TIME → CarbonImmutable (sentinel date 2000-01-01 UTC)
     * TIMESTAMPTZ → CarbonImmutable (timezone preserved from stored offset)
     * ENUM → list<string>
     */
    public function fromStorage(mixed $value, PropertyType $propertyType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($propertyType) {
            PropertyType::Integer,
            PropertyType::Float,
            PropertyType::Boolean,
            PropertyType::String => $value,
            PropertyType::Date => $this->parseDate($value),
            PropertyType::MonthDay => $this->parseMonthDay($value),
            PropertyType::Time => $this->parseTime($value),
            PropertyType::Timestamptz => $this->parseTimestamptz($value),
            PropertyType::Enum => $this->normalizeEnumFromStorage($value),
        };
    }

    /**
     * @param  list<array{value: string, label: string, active: bool}>|null  $enumOptions
     */
    public function formatForDisplay(mixed $value, PropertyType $propertyType, ?array $enumOptions = null): string
    {
        if ($value === null) {
            return '';
        }

        if ($propertyType === PropertyType::Enum) {
            $values = $this->normalizeEnumFromStorage($value);

            return EnumOptions::formatLabels($values, $enumOptions ?? []);
        }

        if ($value instanceof DateTimeInterface) {
            $carbon = CarbonImmutable::instance($value);

            return match ($propertyType) {
                PropertyType::Date => $carbon->toDateString(),
                PropertyType::MonthDay => $carbon->format('m-d'),
                PropertyType::Time => $carbon->format('H:i:s'),
                PropertyType::Timestamptz => $carbon->toIso8601String(),
                default => $carbon->toIso8601String(),
            };
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function isEnumListShape(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        if (array_is_list($value) === false) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                return false;
            }
        }

        return count($value) === count(array_unique($value));
    }

    /**
     * @return list<string>
     */
    private function normalizeEnumFromStorage(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $values[] = $item;
            }
        }

        return $values;
    }

    private function isValidDate(string $value): bool
    {
        if (preg_match(self::DATE_PATTERN, $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function isValidMonthDay(string $value): bool
    {
        if (preg_match(self::MONTH_DAY_PATTERN, $value) !== 1) {
            return false;
        }

        [$month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, self::MONTH_DAY_SENTINEL_YEAR);
    }

    private function isValidTime(string $value): bool
    {
        if (preg_match(self::TIME_PATTERN, $value) !== 1) {
            return false;
        }

        $parts = array_map('intval', explode(':', $value));
        $hour = $parts[0];
        $minute = $parts[1];
        $second = $parts[2] ?? 0;

        return $hour >= 0 && $hour <= 23
            && $minute >= 0 && $minute <= 59
            && $second >= 0 && $second <= 59;
    }

    private function isValidTimestamptz(string $value): bool
    {
        if (preg_match(self::TIMESTAMPTZ_PATTERN, $value) !== 1) {
            return false;
        }

        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeTime(string $value): string
    {
        $parts = explode(':', $value);
        $hour = $parts[0];
        $minute = $parts[1];
        $second = $parts[2] ?? '00';

        return sprintf('%s:%s:%s', $hour, $minute, $second);
    }

    private function normalizeTimestamptz(string $value): string
    {
        return CarbonImmutable::parse($value)->toIso8601String();
    }

    private function parseDate(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (! is_string($value) || ! $this->isValidDate($value)) {
            return $value;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
    }

    private function parseMonthDay(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)
                ->setYear(self::MONTH_DAY_SENTINEL_YEAR)
                ->startOfDay();
        }

        if (! is_string($value) || ! $this->isValidMonthDay($value)) {
            return $value;
        }

        return CarbonImmutable::createFromFormat(
            '!Y-m-d',
            self::MONTH_DAY_SENTINEL_YEAR.'-'.$value,
            'UTC',
        );
    }

    private function parseTime(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)
                ->setDate(2000, 1, 1);
        }

        if (! is_string($value) || ! $this->isValidTime($value)) {
            return $value;
        }

        return CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            self::TIME_SENTINEL_DATE.' '.$this->normalizeTime($value),
            'UTC',
        );
    }

    private function parseTimestamptz(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || ! $this->isValidTimestamptz($value)) {
            return $value;
        }

        return CarbonImmutable::parse($value);
    }
}
