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
}
