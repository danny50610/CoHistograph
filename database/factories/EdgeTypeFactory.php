<?php

namespace Database\Factories;

use App\Models\VertexType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EdgeType>
 */
class EdgeTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'age_label_name' => $this->faker->unique()->word(),
            'start_vertex_id' => VertexType::factory(),
            'end_vertex_id' => VertexType::factory(),
        ];
    }
}
