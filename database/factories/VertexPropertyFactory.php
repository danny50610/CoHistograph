<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\VertexType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VertexProperty>
 */
class VertexPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vertex_type_id' => VertexType::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            // Prefix avoids Cypher reserved words (e.g. "in") from faker->word().
            'age_property_name' => 'p_'.$this->faker->unique()->lexify('????????'),
            'age_property_type' => $this->faker->randomElement(PropertyType::class),
            'locale' => null,
        ];
    }
}
