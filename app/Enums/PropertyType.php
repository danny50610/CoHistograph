<?php

namespace App\Enums;

enum PropertyType: string
{
    case Integer = 'INTEGER';
    case Float = 'FLOAT';
    case Numeric = 'NUMERIC';
    case Boolean = 'BOOLEAN';
    case String = 'STRING';

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
