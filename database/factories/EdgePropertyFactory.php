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
            'age_property_name' => $this->faker->word(),
            'age_property_type' => $this->faker->randomElement(PropertyType::class),
        ];
    }
}
