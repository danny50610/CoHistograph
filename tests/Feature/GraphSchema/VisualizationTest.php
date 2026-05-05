<?php

namespace Tests\Feature\GraphSchema;

use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VisualizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_visualization_page_is_accessible(): void
    {
        $this->actingAs($this->user)
            ->get('/graph-schema/visualization')
            ->assertOk();
    }

    public function test_visualization_page_contains_vertex_and_edge_data(): void
    {
        $vertexA = VertexType::factory()->create(['name' => 'Person']);
        $vertexB = VertexType::factory()->create(['name' => 'Company']);
        EdgeType::factory()->create([
            'name' => 'WorksAt',
            'start_vertex_id' => $vertexA->id,
            'end_vertex_id' => $vertexB->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/graph-schema/visualization')
            ->assertOk();

        $response->assertSee('Person');
        $response->assertSee('Company');
        $response->assertSee('WorksAt');
    }

    public function test_visualization_data_contains_urls(): void
    {
        $vertex = VertexType::factory()->create();

        $response = $this->actingAs($this->user)
            ->get('/graph-schema/visualization')
            ->assertOk();

        $expectedUrl = route('graph-schema.vertex-type.show', $vertex);
        $response->assertSee($expectedUrl);
    }
}
