<?php

namespace App\Enums;

enum PropertyType: string
{
    case Integer = 'INTEGER';
    case Float = 'FLOAT';
    case Numeric = 'NUMERIC';
    case boolean = 'BOOLEAN';
}
