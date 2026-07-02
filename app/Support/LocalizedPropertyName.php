<?php

namespace App\Support;

use App\Models\EdgeProperty;
use App\Models\VertexProperty;
use Illuminate\Support\Str;

class LocalizedPropertyName
{
    public static function baseName(VertexProperty|EdgeProperty $property): string
    {
        if ($property->locale === null) {
            return $property->age_property_name;
        }

        $suffix = '_'.$property->locale;

        return Str::endsWith($property->age_property_name, $suffix)
            ? Str::beforeLast($property->age_property_name, $suffix)
            : $property->age_property_name;
    }

    public static function agePropertyNameForLocale(string $baseName, string $locale): string
    {
        return $baseName.'_'.$locale;
    }
}
