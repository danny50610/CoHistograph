<?php

namespace Database\Factories;

use App\Models\EdgeType;
use App\Models\EdgeTypeVertexPair;
use App\Models\VertexType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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
            // Prefix avoids Cypher reserved words (e.g. "in") from faker->word().
            'age_label_name' => 'l_'.$this->faker->unique()->lexify('????????'),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): Collection|Model
    {
        $startVertexId = $attributes['start_vertex_id'] ?? null;
        $endVertexId = $attributes['end_vertex_id'] ?? null;
        /** @var list<array{start_vertex_id:int|string,end_vertex_id:int|string}>|null $vertexPairs */
        $vertexPairs = $attributes['vertex_pairs'] ?? null;
        unset($attributes['start_vertex_id'], $attributes['end_vertex_id'], $attributes['vertex_pairs']);

        $result = parent::create($attributes, $parent);
        $edgeTypes = $result instanceof Collection ? $result : collect([$result]);

        foreach ($edgeTypes as $edgeType) {
            /** @var EdgeType $edgeType */
            $this->attachVertexPairs($edgeType, $startVertexId, $endVertexId, $vertexPairs);
        }

        return $result;
    }

    /**
     * @param  list<array{start_vertex_id:int|string,end_vertex_id:int|string}>|null  $vertexPairs
     */
    private function attachVertexPairs(
        EdgeType $edgeType,
        mixed $startVertexId,
        mixed $endVertexId,
        ?array $vertexPairs,
    ): void {
        if (is_array($vertexPairs) && $vertexPairs !== []) {
            foreach ($vertexPairs as $pair) {
                EdgeTypeVertexPair::query()->create([
                    'edge_type_id' => $edgeType->id,
                    'start_vertex_id' => (int) $pair['start_vertex_id'],
                    'end_vertex_id' => (int) $pair['end_vertex_id'],
                ]);
            }

            return;
        }

        if ($startVertexId !== null || $endVertexId !== null) {
            EdgeTypeVertexPair::query()->create([
                'edge_type_id' => $edgeType->id,
                'start_vertex_id' => $startVertexId !== null
                    ? $this->resolveVertexTypeId($startVertexId)
                    : VertexType::factory()->create()->id,
                'end_vertex_id' => $endVertexId !== null
                    ? $this->resolveVertexTypeId($endVertexId)
                    : VertexType::factory()->create()->id,
            ]);

            return;
        }

        if ($edgeType->vertexPairs()->doesntExist()) {
            EdgeTypeVertexPair::query()->create([
                'edge_type_id' => $edgeType->id,
                'start_vertex_id' => VertexType::factory()->create()->id,
                'end_vertex_id' => VertexType::factory()->create()->id,
            ]);
        }
    }

    private function resolveVertexTypeId(mixed $value): int
    {
        if ($value instanceof VertexType) {
            return $value->id;
        }

        return (int) $value;
    }
}
