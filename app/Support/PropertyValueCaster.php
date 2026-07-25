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
     * Leap year used as a sentinel when materializing month-day as Carbon.
     * Allows valid Feb 29 values without attaching a real year.
     */
    private const MONTH_DAY_SENTINEL_YEAR = 2000;

    /**
     * ISO-8601 datetime with explicit timezone offset or Z.
     * Examples: 2024-07-22T14:30:00+08:00, 2024-07-22T06:30:00Z
     */
    private const TIMESTAMPTZ_PATTERN = '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|[+-]\d{2}:?\d{2})$/';

    public function matchesType(string $value, PropertyType $propertyType): bool
    {
        return match ($propertyType) {
            PropertyType::Integer => preg_match('/^-?\d+$/', $value) === 1,
            PropertyType::Float => preg_match('/^-?(?:\d+|\d*\.\d+)$/', $value) === 1,
            PropertyType::Boolean => in_array(strtolower($value), ['true', 'false'], true),
            PropertyType::String => true,
            PropertyType::Date => $this->isValidDate($value),
            PropertyType::MonthDay => $this->isValidMonthDay($value),
            PropertyType::Timestamptz => $this->isValidTimestamptz($value),
        };
    }

    /**
     * Convert a revision/input string into the PHP/AGE storage value.
     *
     * DATE / MONTH_DAY / TIMESTAMPTZ stay as normalized strings in AGE (agtype string).
     */
    public function toStorage(string $value, PropertyType $propertyType): int|float|bool|string
    {
        if (! $this->matchesType($value, $propertyType)) {
            throw new InvalidArgumentException("Value [{$value}] does not match property type [{$propertyType->value}].");
        }

        return match ($propertyType) {
            PropertyType::Integer => (int) $value,
            PropertyType::Float => (float) $value,
            PropertyType::Boolean => strtolower($value) === 'true',
            PropertyType::String => $value,
            PropertyType::Date => $value,
            PropertyType::MonthDay => $value,
            PropertyType::Timestamptz => $this->normalizeTimestamptz($value),
        };
    }

    /**
     * Convert a value read from AGE into the PHP object/scalar for this property type.
     *
     * DATE → CarbonImmutable (date-only, midnight UTC)
     * MONTH_DAY → CarbonImmutable (sentinel year 2000, midnight UTC)
     * TIMESTAMPTZ → CarbonImmutable (timezone preserved from stored offset)
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
            PropertyType::Timestamptz => $this->parseTimestamptz($value),
        };
    }

    public function formatForDisplay(mixed $value, PropertyType $propertyType): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $carbon = CarbonImmutable::instance($value);

            return match ($propertyType) {
                PropertyType::Date => $carbon->toDateString(),
                PropertyType::MonthDay => $carbon->format('m-d'),
                PropertyType::Timestamptz => $carbon->toIso8601String(),
                default => $carbon->toIso8601String(),
            };
        }

        return (string) $value;
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
