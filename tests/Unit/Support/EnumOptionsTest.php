<?php

namespace Tests\Unit\Support;

use App\Enums\PropertyType;
use App\Support\EnumOptions;
use App\Support\PropertyValueCaster;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumOptionsTest extends TestCase
{
    /**
     * @return list<array{value: string, label: string, active: bool}>
     */
    private function sampleOptions(): array
    {
        return [
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => false],
            ['value' => 'pop', 'label' => '流行', 'active' => true],
        ];
    }

    #[Test]
    public function validate_selected_rejects_empty_and_duplicates(): void
    {
        $options = $this->sampleOptions();

        $this->assertNotEmpty(EnumOptions::validateSelected([], $options, [], true));
        $this->assertNotEmpty(EnumOptions::validateSelected(['rock', 'rock'], $options, [], true));
    }

    #[Test]
    public function validate_selected_enforces_grandfather_clause(): void
    {
        $options = $this->sampleOptions();

        $this->assertSame([], EnumOptions::validateSelected(['rock', 'jazz'], $options, ['jazz'], false));
        $this->assertNotEmpty(EnumOptions::validateSelected(['jazz'], $options, [], true));
        $this->assertNotEmpty(EnumOptions::validateSelected(['jazz'], $options, ['rock'], false));
        $this->assertSame([], EnumOptions::validateSelected(['rock', 'orphan'], $options, ['orphan'], false));
        $this->assertNotEmpty(EnumOptions::validateSelected(['rock', 'orphan'], $options, [], false));
    }

    #[Test]
    public function validate_selected_enforces_min_and_max_counts(): void
    {
        $options = $this->sampleOptions();

        $this->assertNotEmpty(EnumOptions::validateSelected(
            ['rock'],
            $options,
            [],
            true,
            minSelections: 2,
            maxSelections: null,
        ));
        $this->assertSame([], EnumOptions::validateSelected(
            ['rock', 'pop'],
            $options,
            [],
            true,
            minSelections: 2,
            maxSelections: 2,
        ));
        $this->assertNotEmpty(EnumOptions::validateSelected(
            ['rock', 'pop', 'jazz'],
            $options,
            ['jazz'],
            false,
            minSelections: 1,
            maxSelections: 2,
        ));
    }

    #[Test]
    public function validate_schema_selection_limits_requires_enough_active_options(): void
    {
        $options = $this->sampleOptions();

        $this->assertSame([], EnumOptions::validateSchemaSelectionLimits($options, 2, null));
        $this->assertNotEmpty(EnumOptions::validateSchemaSelectionLimits($options, 3, null));
        $this->assertNotEmpty(EnumOptions::validateSchemaSelectionLimits($options, 2, 1));
    }

    #[Test]
    public function sort_and_format_labels_follow_definition_order(): void
    {
        $options = $this->sampleOptions();

        $this->assertSame(
            ['rock', 'jazz', 'pop'],
            EnumOptions::sortSelectedByDefinition(['pop', 'jazz', 'rock'], $options),
        );
        $this->assertSame('搖滾、流行', EnumOptions::formatLabels(['pop', 'rock'], $options));
        $this->assertSame(
            ['rock', 'pop'],
            (new PropertyValueCaster)->toStorage(['pop', 'rock'], PropertyType::Enum, $options),
        );
        $this->assertSame(
            '搖滾、爵士（已停用）、流行',
            EnumOptions::formatSchemaLabels($options),
        );
    }

    #[Test]
    public function caster_handles_enum_lists(): void
    {
        $caster = new PropertyValueCaster;
        $options = $this->sampleOptions();

        $this->assertTrue($caster->matchesType(['rock', 'pop'], PropertyType::Enum));
        $this->assertFalse($caster->matchesType([], PropertyType::Enum));
        $this->assertFalse($caster->matchesType(['rock', 'rock'], PropertyType::Enum));
        $this->assertSame('搖滾、流行', $caster->formatForDisplay(['pop', 'rock'], PropertyType::Enum, $options));
    }

    #[Test]
    public function caster_accepts_native_scalars_for_b1_compat(): void
    {
        $caster = new PropertyValueCaster;

        $this->assertTrue($caster->matchesType(42, PropertyType::Integer));
        $this->assertTrue($caster->matchesType(true, PropertyType::Boolean));
        $this->assertSame(42, $caster->toStorage(42, PropertyType::Integer));
        $this->assertTrue($caster->toStorage(true, PropertyType::Boolean));
    }

    #[Test]
    public function caster_rejects_invalid_enum_to_storage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PropertyValueCaster)->toStorage('rock', PropertyType::Enum);
    }

    #[Test]
    public function normalize_trims_and_defaults_active(): void
    {
        $this->assertSame([], EnumOptions::normalize(null));
        $this->assertSame(
            [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => false],
            ],
            EnumOptions::normalize([
                ['value' => ' rock ', 'label' => ' 搖滾 '],
                ['value' => 'jazz', 'label' => '爵士', 'active' => '0'],
            ]),
        );
    }

    #[Test]
    public function normalize_rejects_non_array_option(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EnumOptions::normalize(['rock']);
    }

    #[Test]
    public function is_valid_option_value_and_requires_enum_options(): void
    {
        $this->assertTrue(EnumOptions::isValidOptionValue('rock'));
        $this->assertTrue(EnumOptions::isValidOptionValue('a+b-c_1'));
        $this->assertFalse(EnumOptions::isValidOptionValue(''));
        $this->assertFalse(EnumOptions::isValidOptionValue('Rock'));
        $this->assertFalse(EnumOptions::isValidOptionValue('has space'));
        $this->assertTrue(EnumOptions::requiresEnumOptions(PropertyType::Enum));
        $this->assertFalse(EnumOptions::requiresEnumOptions(PropertyType::String));
    }

    #[Test]
    public function values_active_values_and_empty_schema_labels(): void
    {
        $options = $this->sampleOptions();

        $this->assertSame(['rock', 'jazz', 'pop'], EnumOptions::values($options));
        $this->assertSame(['rock', 'pop'], EnumOptions::activeValues($options));
        $this->assertSame('', EnumOptions::formatSchemaLabels(null));
        $this->assertSame('', EnumOptions::formatSchemaLabels([]));
    }

    #[Test]
    public function caster_from_storage_normalizes_enum_lists(): void
    {
        $caster = new PropertyValueCaster;

        $this->assertSame(['rock', 'jazz'], $caster->fromStorage(['rock', '', 'jazz', 1], PropertyType::Enum));
        $this->assertSame([], $caster->fromStorage('rock', PropertyType::Enum));
        $this->assertNull($caster->fromStorage(null, PropertyType::Enum));
    }

    #[Test]
    public function validate_schema_selection_limits_rejects_out_of_range(): void
    {
        $options = $this->sampleOptions();

        $this->assertNotEmpty(EnumOptions::validateSchemaSelectionLimits($options, 0, null));
        $this->assertNotEmpty(EnumOptions::validateSchemaSelectionLimits($options, 1, 0));
        $this->assertNotEmpty(EnumOptions::validateSchemaSelectionLimits($options, 1, 256));
    }
}
