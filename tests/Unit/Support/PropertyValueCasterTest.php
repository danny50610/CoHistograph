<?php

namespace Tests\Unit\Support;

use App\Enums\PropertyType;
use App\Support\PropertyValueCaster;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PropertyValueCasterTest extends TestCase
{
    private PropertyValueCaster $caster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caster = new PropertyValueCaster;
    }

    #[Test]
    #[DataProvider('validValuesProvider')]
    public function matches_type_accepts_valid_values(PropertyType $type, string $value): void
    {
        $this->assertTrue($this->caster->matchesType($value, $type));
    }

    #[Test]
    #[DataProvider('invalidValuesProvider')]
    public function matches_type_rejects_invalid_values(PropertyType $type, string $value): void
    {
        $this->assertFalse($this->caster->matchesType($value, $type));
    }

    #[Test]
    public function to_storage_keeps_date_and_timestamptz_as_strings(): void
    {
        $this->assertSame('2024-07-22', $this->caster->toStorage('2024-07-22', PropertyType::Date));
        $this->assertSame(
            '2024-07-22T14:30:00+08:00',
            $this->caster->toStorage('2024-07-22T14:30:00+08:00', PropertyType::Timestamptz),
        );
        $this->assertSame(
            '2024-07-22T06:30:00+00:00',
            $this->caster->toStorage('2024-07-22T06:30:00Z', PropertyType::Timestamptz),
        );
    }

    #[Test]
    public function to_storage_casts_scalar_types(): void
    {
        $this->assertSame(42, $this->caster->toStorage('42', PropertyType::Integer));
        $this->assertSame(3.14, $this->caster->toStorage('3.14', PropertyType::Float));
        $this->assertTrue($this->caster->toStorage('true', PropertyType::Boolean));
        $this->assertSame('hello', $this->caster->toStorage('hello', PropertyType::String));
    }

    #[Test]
    public function to_storage_throws_for_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->caster->toStorage('not-a-date', PropertyType::Date);
    }

    #[Test]
    public function from_storage_converts_date_to_carbon_immutable(): void
    {
        $result = $this->caster->fromStorage('2024-07-22', PropertyType::Date);

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertSame('2024-07-22', $result->toDateString());
        $this->assertSame('UTC', $result->timezoneName);
    }

    #[Test]
    public function from_storage_converts_timestamptz_preserving_offset(): void
    {
        $result = $this->caster->fromStorage('2024-07-22T14:30:00+08:00', PropertyType::Timestamptz);

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertSame(14, $result->hour);
        $this->assertSame(480, $result->offsetMinutes);
    }

    #[Test]
    public function from_storage_returns_null_for_null(): void
    {
        $this->assertNull($this->caster->fromStorage(null, PropertyType::Date));
    }

    #[Test]
    public function from_storage_leaves_corrupt_temporal_strings_unchanged(): void
    {
        $this->assertSame('bad', $this->caster->fromStorage('bad', PropertyType::Date));
        $this->assertSame('bad', $this->caster->fromStorage('bad', PropertyType::Timestamptz));
    }

    #[Test]
    public function format_for_display_uses_type_specific_formats(): void
    {
        $date = $this->caster->fromStorage('2024-07-22', PropertyType::Date);
        $ts = $this->caster->fromStorage('2024-07-22T14:30:00+08:00', PropertyType::Timestamptz);

        $this->assertSame('2024-07-22', $this->caster->formatForDisplay($date, PropertyType::Date));
        $this->assertSame('2024-07-22T14:30:00+08:00', $this->caster->formatForDisplay($ts, PropertyType::Timestamptz));
        $this->assertSame('', $this->caster->formatForDisplay(null, PropertyType::Date));
        $this->assertSame('42', $this->caster->formatForDisplay(42, PropertyType::Integer));
    }

    /**
     * @return array<string, array{0: PropertyType, 1: string}>
     */
    public static function validValuesProvider(): array
    {
        return [
            'integer' => [PropertyType::Integer, '-12'],
            'float' => [PropertyType::Float, '3.5'],
            'boolean' => [PropertyType::Boolean, 'false'],
            'string' => [PropertyType::String, 'anything'],
            'date' => [PropertyType::Date, '2024-02-29'],
            'timestamptz offset' => [PropertyType::Timestamptz, '2024-07-22T14:30:00+08:00'],
            'timestamptz z' => [PropertyType::Timestamptz, '2024-07-22T06:30:00Z'],
            'timestamptz compact offset' => [PropertyType::Timestamptz, '2024-07-22T14:30:00+0800'],
        ];
    }

    /**
     * @return array<string, array{0: PropertyType, 1: string}>
     */
    public static function invalidValuesProvider(): array
    {
        return [
            'integer float' => [PropertyType::Integer, '1.5'],
            'float letters' => [PropertyType::Float, 'abc'],
            'boolean yes' => [PropertyType::Boolean, 'yes'],
            'date invalid calendar' => [PropertyType::Date, '2023-02-29'],
            'date with time' => [PropertyType::Date, '2024-07-22T00:00:00Z'],
            'timestamptz without tz' => [PropertyType::Timestamptz, '2024-07-22T14:30:00'],
            'timestamptz date only' => [PropertyType::Timestamptz, '2024-07-22'],
        ];
    }
}
