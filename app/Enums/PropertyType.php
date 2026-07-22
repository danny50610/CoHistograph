<?php

namespace App\Enums;

enum PropertyType: string
{
    case Integer = 'INTEGER';
    case Float = 'FLOAT';
    case Boolean = 'BOOLEAN';
    case String = 'STRING';
    /** Date-only value stored as `Y-m-d` string in AGE. */
    case Date = 'DATE';
    /** Instant with timezone, stored as ISO-8601 string with offset in AGE. */
    case Timestamptz = 'TIMESTAMPTZ';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->value],
            self::cases(),
        );
    }
}
