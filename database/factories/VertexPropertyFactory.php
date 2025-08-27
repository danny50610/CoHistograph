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
            'age_property_name' => $this->faker->unique()->word(),
            'age_property_type' => $this->faker->randomElement(PropertyType::class),
        ];
    }
}
