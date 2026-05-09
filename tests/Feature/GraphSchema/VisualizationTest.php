<?php

namespace Tests\Feature\GraphSchema;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
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
            ->get(route('graph-schema.visualization'))
            ->assertOk();
    }

    public function test_visualization_page_contains_vertex_and_edge_data(): void
    {
        $vertexA = VertexType::factory()->create([
            'name' => 'Person',
            'age_label_name' => 'person_label',
            'description' => 'Person vertex description',
        ]);
        $vertexB = VertexType::factory()->create([
            'name' => 'Company',
            'age_label_name' => 'company_label',
            'description' => 'Company vertex description',
        ]);

        VertexProperty::factory()->create([
            'vertex_type_id' => $vertexA->id,
            'name' => 'full_name',
            'age_property_name' => 'fullName',
            'age_property_type' => PropertyType::String,
        ]);

        $edgeType = EdgeType::factory()->create([
            'name' => 'WorksAt',
            'age_label_name' => 'works_at',
            'description' => 'Employment relationship',
            'start_vertex_id' => $vertexA->id,
            'end_vertex_id' => $vertexB->id,
        ]);

        EdgeProperty::factory()->create([
            'edge_type_id' => $edgeType->id,
            'name' => 'since_year',
            'age_property_name' => 'sinceYear',
            'age_property_type' => PropertyType::Integer,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('graph-schema.visualization'))
            ->assertOk();

        $response->assertSee('Person');
        $response->assertSee('Company');
        $response->assertSee('WorksAt');
        $response->assertSee('"age_label_name":"person_label"', false);
        $response->assertSee('"description":"Person vertex description"', false);
        $response->assertSee('"properties":[{"name":"full_name","age_property_name":"fullName","age_property_type":"STRING"}]', false);
        $response->assertSee('"age_label_name":"works_at"', false);
        $response->assertSee('"description":"Employment relationship"', false);
        $response->assertSee('"start_vertex_name":"Person"', false);
        $response->assertSee('"end_vertex_name":"Company"', false);
        $response->assertSee('"properties":[{"name":"since_year","age_property_name":"sinceYear","age_property_type":"INTEGER"}]', false);
    }

    public function test_visualization_data_contains_urls(): void
    {
        $vertex = VertexType::factory()->create();

        $expectedUrl = route('graph-schema.vertex-type.show', $vertex);

        $response = $this->actingAs($this->user)
            ->get(route('graph-schema.visualization'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('GraphSchema/Visualization')
                ->has('vertexTypeList')
            );

        $vertexUrls = collect($response->inertiaProps('vertexTypeList'))->pluck('url');
        $this->assertContains($expectedUrl, $vertexUrls->all());
    }
}
