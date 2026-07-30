<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\EdgeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EdgeProperty>
 */
class EdgePropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edge_type_id' => EdgeType::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            // Prefix avoids Cypher reserved words (e.g. "in") from faker->word().
            'age_property_name' => 'p_'.$this->faker->unique()->lexify('????????'),
            'age_property_type' => $this->faker->randomElement([
                PropertyType::Integer,
                PropertyType::Float,
                PropertyType::Boolean,
                PropertyType::String,
                PropertyType::Date,
                PropertyType::MonthDay,
                PropertyType::Timestamptz,
            ]),
            'locale' => null,
            'enum_options' => null,
            'min_selections' => null,
            'max_selections' => null,
        ];
    }

    /**
     * @param  list<array{value: string, label: string, active?: bool}>|null  $options
     */
    public function enum(?array $options = null): static
    {
        return $this->state(fn (): array => [
            'age_property_type' => PropertyType::Enum,
            'locale' => null,
            'enum_options' => $options ?? [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ],
            'min_selections' => 1,
            'max_selections' => null,
        ]);
    }
}
