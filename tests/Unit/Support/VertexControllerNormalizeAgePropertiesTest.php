<?php

namespace Tests\Unit\Support;

use App\Http\Controllers\Graph\VertexController;
use Tests\TestCase;

class VertexControllerNormalizeAgePropertiesTest extends TestCase
{
    public function test_normalize_age_properties_converts_object_and_non_array_values(): void
    {
        $controller = new class extends VertexController
        {
            public function callNormalizeAgeProperties(mixed $properties): array
            {
                return $this->normalizeAgeProperties($properties);
            }
        };

        $this->assertSame(
            ['name_zh_tw' => '李白'],
            $controller->callNormalizeAgeProperties((object) ['name_zh_tw' => '李白']),
        );

        $this->assertSame([], $controller->callNormalizeAgeProperties(null));
        $this->assertSame([], $controller->callNormalizeAgeProperties('invalid'));
    }
}
