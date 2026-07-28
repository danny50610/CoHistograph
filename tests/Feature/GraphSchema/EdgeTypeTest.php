<?php

namespace Tests\Feature\GraphSchema;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EdgeTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_create_success()
    {
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'participated_in',
                'reverse_name' => 'had_participant',
                'age_label_name' => 'participated_in',
                'description' => 'A participation edge',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $edgeType = EdgeType::where('name', 'participated_in')->first();
        $this->assertNotNull($edgeType);
        $this->assertEquals('participated_in', $edgeType->age_label_name);
        $edgeType->load('vertexPairs');
        $this->assertCount(1, $edgeType->vertexPairs);
        $this->assertEquals($startVertex->id, $edgeType->vertexPairs->first()->start_vertex_id);
        $this->assertEquals($endVertex->id, $edgeType->vertexPairs->first()->end_vertex_id);
    }

    public function test_create_success_when_name_matches_vertex_type_display_name()
    {
        VertexType::factory()->create(['name' => 'Person']);
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'Person',
                'age_label_name' => 'person_edge',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertNotNull(EdgeType::where('name', 'Person')->first());
    }

    public function test_create_success_when_start_and_end_vertex_are_same()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'knows',
                'reverse_name' => 'known_by',
                'age_label_name' => 'knows',
                'description' => 'Self-referencing edge',
                'vertex_pairs' => [['start_vertex_id' => $vertexType->id, 'end_vertex_id' => $vertexType->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $edgeType = EdgeType::where('name', 'knows')->first();
        $this->assertNotNull($edgeType);
        $edgeType->load('vertexPairs');
        $this->assertCount(1, $edgeType->vertexPairs);
        $this->assertEquals($vertexType->id, $edgeType->vertexPairs->first()->start_vertex_id);
        $this->assertEquals($vertexType->id, $edgeType->vertexPairs->first()->end_vertex_id);
    }

    public function test_create_success_when_multiple_edge_types_share_same_start_and_end_vertices()
    {
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'participated_in',
                'age_label_name' => 'participated_in',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'organized',
                'age_label_name' => 'organized',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertEquals(2, EdgeType::query()
            ->whereHas('vertexPairs', function ($query) use ($startVertex, $endVertex) {
                $query->where('start_vertex_id', $startVertex->id)
                    ->where('end_vertex_id', $endVertex->id);
            })
            ->count());
    }

    public function test_create_fail_when_age_label_name_clashes_with_vertex_type()
    {
        VertexType::factory()->create(['age_label_name' => 'person']);
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'SomeEdge',
                'age_label_name' => 'person',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_create_fail_when_name_clashes_with_edge_type()
    {
        EdgeType::factory()->create(['name' => 'participated_in']);
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'participated_in',
                'age_label_name' => 'participated_in_edge',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);
    }

    public function test_create_fail_when_age_label_name_clashes_with_edge_type()
    {
        EdgeType::factory()->create(['age_label_name' => 'participated_in']);
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'SomeEdge',
                'age_label_name' => 'participated_in',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_create_fail_when_age_label_name_invalid()
    {
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'some_edge',
                'age_label_name' => 'Invalid-Label',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_create_fail_when_start_vertex_id_missing()
    {
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'some_edge',
                'age_label_name' => 'some_edge',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => '', 'end_vertex_id' => $endVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['vertex_pairs.0.start_vertex_id']);
    }

    public function test_create_fail_when_end_vertex_id_invalid()
    {
        $startVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'some_edge',
                'age_label_name' => 'some_edge',
                'description' => '',
                'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => 99999]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['vertex_pairs.0.end_vertex_id']);
    }

    public function test_create_success_with_multiple_vertex_pairs()
    {
        $startVertex = VertexType::factory()->create();
        $endVertexA = VertexType::factory()->create();
        $endVertexB = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'supports',
                'age_label_name' => 'supports',
                'description' => 'Support subject',
                'vertex_pairs' => [
                    ['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertexA->id],
                    ['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertexB->id],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $edgeType = EdgeType::where('age_label_name', 'supports')->first();
        $this->assertNotNull($edgeType);
        $edgeType->load('vertexPairs');
        $this->assertCount(2, $edgeType->vertexPairs);
        $this->assertTrue($edgeType->vertexPairs->contains(
            fn ($pair) => $pair->start_vertex_id === $startVertex->id && $pair->end_vertex_id === $endVertexA->id
        ));
        $this->assertTrue($edgeType->vertexPairs->contains(
            fn ($pair) => $pair->start_vertex_id === $startVertex->id && $pair->end_vertex_id === $endVertexB->id
        ));
    }

    public function test_create_fail_when_vertex_pairs_are_duplicated()
    {
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'supports',
                'age_label_name' => 'supports',
                'description' => '',
                'vertex_pairs' => [
                    ['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id],
                    ['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['vertex_pairs.1.start_vertex_id']);
    }

    public function test_update_success()
    {
        $edgeType = EdgeType::factory()->create();
        $newStartVertex = VertexType::factory()->create();
        $newEndVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => 'updated_edge',
                'reverse_name' => 'reversed',
                'age_label_name' => 'updated_edge',
                'description' => 'Updated description',
                'vertex_pairs' => [['start_vertex_id' => $newStartVertex->id, 'end_vertex_id' => $newEndVertex->id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedEdgeType = EdgeType::find($edgeType->id);
        $this->assertNotNull($updatedEdgeType);
        $this->assertEquals('updated_edge', $updatedEdgeType->name);
        $this->assertEquals('updated_edge', $updatedEdgeType->age_label_name);
        $updatedEdgeType->load('vertexPairs');
        $this->assertCount(1, $updatedEdgeType->vertexPairs);
        $this->assertEquals($newStartVertex->id, $updatedEdgeType->vertexPairs->first()->start_vertex_id);
        $this->assertEquals($newEndVertex->id, $updatedEdgeType->vertexPairs->first()->end_vertex_id);
    }

    public function test_update_success_when_name_matches_vertex_type_display_name()
    {
        $edgeType = EdgeType::factory()->create();
        VertexType::factory()->create(['name' => 'Person']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => 'Person',
                'age_label_name' => $edgeType->age_label_name,
                'description' => $edgeType->description,
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertEquals('Person', $edgeType->fresh()->name);
    }

    public function test_update_fail_when_age_label_name_clashes_with_vertex_type()
    {
        $edgeType = EdgeType::factory()->create();
        VertexType::factory()->create(['age_label_name' => 'person']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => $edgeType->name,
                'age_label_name' => 'person',
                'description' => $edgeType->description,
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_update_fail_when_name_clashes_with_edge_type()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeType::factory()->create(['name' => 'participated_in']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => 'participated_in',
                'age_label_name' => $edgeType->age_label_name,
                'description' => $edgeType->description,
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);
    }

    public function test_update_fail_when_age_label_name_clashes_with_edge_type()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeType::factory()->create(['age_label_name' => 'participated_in']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => $edgeType->name,
                'age_label_name' => 'participated_in',
                'description' => $edgeType->description,
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_update_success_when_keeping_same_age_label_name()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => $edgeType->name,
                'reverse_name' => 'updated reverse',
                'age_label_name' => $edgeType->age_label_name,
                'description' => 'Updated description only',
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedEdgeType = EdgeType::find($edgeType->id);
        $this->assertNotNull($updatedEdgeType);
        $this->assertEquals($edgeType->age_label_name, $updatedEdgeType->age_label_name);
        $this->assertEquals('Updated description only', $updatedEdgeType->description);
    }

    public function test_destroy_success()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}")
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($edgeType);
    }

    public function test_destroy_fail_when_has_properties()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($edgeType);
    }

    public function test_destroy_fail_when_has_graph_edges_data()
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'destroy_edge_start_vt']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'destroy_edge_end_vt']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'destroy_edge_et',
            'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType) {
                return $builder->createNode('a', $edgeType->vertexPairs->first()->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name)
                    ->withCreateNode('b', $edgeType->vertexPairs->first()->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($edgeType);
    }

    public function test_update_fail_when_age_label_name_changes_and_graph_data_exists(): void
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'lock_edge_start_vt']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'lock_edge_end_vt']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'lock_edge_et',
            'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType) {
                return $builder->createNode('a', $edgeType->vertexPairs->first()->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name)
                    ->withCreateNode('b', $edgeType->vertexPairs->first()->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => $edgeType->name,
                'age_label_name' => 'renamed_edge_et',
                'description' => $edgeType->description,
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'age_label_name' => '圖資料庫中已有此類型的資料，無法變更 Label 名稱',
            ]);

        $this->assertSame('lock_edge_et', $edgeType->fresh()->age_label_name);
    }

    public function test_update_success_when_keeping_same_age_label_name_with_graph_data(): void
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'keep_edge_start_vt']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'keep_edge_end_vt']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'keep_edge_et',
            'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType) {
                return $builder->createNode('a', $edgeType->vertexPairs->first()->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name)
                    ->withCreateNode('b', $edgeType->vertexPairs->first()->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => 'Updated Edge',
                'reverse_name' => 'Updated Reverse',
                'age_label_name' => 'keep_edge_et',
                'description' => 'Updated description',
                'vertex_pairs' => [['start_vertex_id' => $edgeType->vertexPairs->first()->start_vertex_id, 'end_vertex_id' => $edgeType->vertexPairs->first()->end_vertex_id]],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updated = $edgeType->fresh();
        $this->assertSame('Updated Edge', $updated->name);
        $this->assertSame('keep_edge_et', $updated->age_label_name);
        $this->assertSame('Updated description', $updated->description);
    }

    public function test_edit_shows_readonly_age_label_name_when_graph_data_exists(): void
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'readonly_edge_start_vt']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'readonly_edge_end_vt']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'readonly_edge_et',
            'vertex_pairs' => [['start_vertex_id' => $startVertex->id, 'end_vertex_id' => $endVertex->id]],
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType) {
                return $builder->createNode('a', $edgeType->vertexPairs->first()->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name)
                    ->withCreateNode('b', $edgeType->vertexPairs->first()->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->get("/graph-schema/edge-type/{$edgeType->id}/edit")
            ->assertOk()
            ->assertSee('readonly', false)
            ->assertSee('圖資料庫中已有此類型的資料，無法變更 Label 名稱');
    }

    public function test_show_groups_localized_properties(): void
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create([
            'name' => '角色說明',
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);
        EdgeProperty::factory()->for($edgeType)->create([
            'name' => 'Role',
            'age_property_name' => 'role_en_us',
            'locale' => 'en_us',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/graph-schema/edge-type/{$edgeType->id}");

        $response->assertOk();
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertSame(1, substr_count($content, 'role_zh_tw'));
        $this->assertSame(1, substr_count($content, 'role_en_us'));
        $this->assertStringContainsString('繁體中文', $content);
        $this->assertStringContainsString('English', $content);
    }
}
