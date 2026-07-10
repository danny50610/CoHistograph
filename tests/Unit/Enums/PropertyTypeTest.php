<?php

namespace Tests\Unit\Enums;

use App\Enums\PropertyType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PropertyTypeTest extends TestCase
{
    #[Test]
    public function select_options_include_all_cases(): void
    {
        $options = PropertyType::selectOptions();

        $this->assertCount(count(PropertyType::cases()), $options);

        foreach (PropertyType::cases() as $type) {
            $this->assertContains(
                ['value' => $type->value, 'label' => $type->value],
                $options,
            );
        }
    }
}
