<?php

namespace Database\Factories;

use App\Enums\RevisionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Revision>
 */
class RevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'status' => RevisionStatus::Draft,
            'user_id' => User::factory(),
            'is_ai_assisted' => false,
        ];
    }

    public function aiAssisted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_ai_assisted' => true,
        ]);
    }
}
