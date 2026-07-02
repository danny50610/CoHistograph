<?php

namespace Tests\Feature\Graph;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VertexShowTest extends TestCase
{
    use DatabaseTransactions;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');
    }

    public function test_show_displays_grouped_vertex_properties(): void
    {
        $vertexType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Name',
            'age_property_name' => 'name_en_us',
            'locale' => 'en_us',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '出生年份',
            'age_property_name' => 'birth_year',
            'locale' => null,
            'age_property_type' => PropertyType::Integer,
        ]);

        $vertexId = $this->createAgeVertex($vertexType->age_label_name, [
            'name_zh_tw' => '李白',
            'name_en_us' => 'Li Bai',
            'birth_year' => 701,
        ]);

        $this->get(route('graph.vertex.show', ['vertex' => $vertexId]))
            ->assertOk()
            ->assertSee('Vertex - '.$vertexType->name.' - 李白', false)
            ->assertSee('繁體中文：李白')
            ->assertSee('English：Li Bai')
            ->assertSee('出生年份')
            ->assertSee('701')
            ->assertDontSee('name_zh_tw');
    }

    public function test_show_displays_grouped_edge_properties_per_edge_instance(): void
    {
        $personType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($personType)->create([
            'name' => '名稱',
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $songType = VertexType::factory()->create([
            'age_label_name' => $this->graphLabel(),
            'show_property_name' => 'title',
        ]);
        VertexProperty::factory()->for($songType)->create([
            'name' => '曲名',
            'age_property_name' => 'title',
            'locale' => null,
        ]);

        $edgeLabel = $this->graphLabel();
        $edgeType = EdgeType::factory()->create([
            'name' => '參與',
            'age_label_name' => $edgeLabel,
            'start_vertex_id' => $personType->id,
            'end_vertex_id' => $songType->id,
        ]);
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

        $personId = $this->createAgeVertex($personType->age_label_name, ['name' => '主唱']);
        $songId = $this->createAgeVertex($songType->age_label_name, ['title' => '某歌曲']);

        $this->createAgeEdge($edgeLabel, $personId, $songId, [
            'role_zh_tw' => '主唱',
            'role_en_us' => 'Lead vocalist',
        ]);

        $this->get(route('graph.vertex.show', ['vertex' => $personId]))
            ->assertOk()
            ->assertSee('參與')
            ->assertSee('某歌曲')
            ->assertSee('繁體中文：主唱')
            ->assertSee('English：Lead vocalist');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createAgeVertex(string $label, array $properties = []): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $properties) {
                return $builder->createNode('v', $label, $properties)->return('v');
            })
            ->first();

        return (int) $result->v->id;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createAgeEdge(string $label, int $startVertexId, int $endVertexId, array $properties = []): int
    {
        $result = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label, $startVertexId, $endVertexId, $properties) {
                $builder = $builder
                    ->matchNode('s')
                    ->where('id(s)', '=', $startVertexId)
                    ->matchNode('t')
                    ->where('id(t)', '=', $endVertexId)
                    ->createRaw("(s)-[e:{$label}]->(t)");

                if ($properties !== []) {
                    $edgeProperties = [];
                    foreach ($properties as $key => $value) {
                        $edgeProperties["e.{$key}"] = $value;
                    }
                    $builder = $builder->set($edgeProperties);
                }

                return $builder->return('e');
            })
            ->first();

        return (int) $result->e->id;
    }

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
