<?php

namespace Database\Factories;

use App\Models\EdgeType;
use App\Models\VertexType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EdgeTypeVertexPair>
 */
class EdgeTypeVertexPairFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edge_type_id' => EdgeType::factory(),
            'start_vertex_id' => VertexType::factory(),
            'end_vertex_id' => VertexType::factory(),
        ];
    }
}
