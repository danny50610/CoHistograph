<?php

namespace Database\Factories;

use App\Models\EdgeType;
use App\Models\EdgeTypeVertexPair;
use App\Models\VertexType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EdgeType>
 */
class EdgeTypeFactory extends Factory
{
    /**
     * Pending endpoint hints keyed by spl_object_id of the unsaved EdgeType.
     *
     * @var array<int, array{start_vertex_id:mixed,end_vertex_id:mixed,vertex_pairs:mixed}>
     */
    private static array $pendingEndpoints = [];

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

    public function configure(): static
    {
        return $this
            ->afterMaking(function (EdgeType $edgeType): void {
                $attributes = $edgeType->getAttributes();

                self::$pendingEndpoints[spl_object_id($edgeType)] = [
                    'start_vertex_id' => $attributes['start_vertex_id'] ?? null,
                    'end_vertex_id' => $attributes['end_vertex_id'] ?? null,
                    'vertex_pairs' => $attributes['vertex_pairs'] ?? null,
                ];

                $edgeType->offsetUnset('start_vertex_id');
                $edgeType->offsetUnset('end_vertex_id');
                $edgeType->offsetUnset('vertex_pairs');
            })
            ->afterCreating(function (EdgeType $edgeType): void {
                $objectId = spl_object_id($edgeType);
                $pending = self::$pendingEndpoints[$objectId] ?? [
                    'start_vertex_id' => null,
                    'end_vertex_id' => null,
                    'vertex_pairs' => null,
                ];
                unset(self::$pendingEndpoints[$objectId]);

                $this->attachVertexPairs(
                    $edgeType,
                    $pending['start_vertex_id'],
                    $pending['end_vertex_id'],
                    is_array($pending['vertex_pairs']) ? $pending['vertex_pairs'] : null,
                );
            });
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
