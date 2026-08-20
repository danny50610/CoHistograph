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

    #[Test]
    public function includes_date_month_day_time_and_timestamptz(): void
    {
        $this->assertSame('DATE', PropertyType::Date->value);
        $this->assertSame('MONTH_DAY', PropertyType::MonthDay->value);
        $this->assertSame('TIME', PropertyType::Time->value);
        $this->assertSame('TIMESTAMPTZ', PropertyType::Timestamptz->value);
        $this->assertSame('ENUM', PropertyType::Enum->value);
        $this->assertCount(9, PropertyType::cases());
        $this->assertFalse(PropertyType::Enum->allowsLocale());
        $this->assertTrue(PropertyType::String->allowsLocale());
        $this->assertTrue(PropertyType::Time->allowsLocale());
    }
}
