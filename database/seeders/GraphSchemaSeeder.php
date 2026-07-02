<?php

namespace Database\Seeders;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GraphSchemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $vertexTypes = VertexType::factory()
                ->count(20)
                ->has(VertexProperty::factory()->count(2), 'properties')
                ->create();

            $usedCombinations = [];
            EdgeType::factory()
                ->count(30)
                ->state(function () use ($vertexTypes, &$usedCombinations) {
                    do {
                        $startVertexId = $vertexTypes->random()->id;
                        $endVertexId = $vertexTypes->random()->id;
                        $combination = "{$startVertexId}-{$endVertexId}";
                    } while (in_array($combination, $usedCombinations));

                    $usedCombinations[] = $combination;

                    return [
                        'start_vertex_id' => $startVertexId,
                        'end_vertex_id' => $endVertexId,
                    ];
                })
                ->has(EdgeProperty::factory()->count(2), 'properties')
                ->create();
        });
    }
}
