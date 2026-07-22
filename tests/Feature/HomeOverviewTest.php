<?php

namespace Tests\Feature;

use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeOverviewTest extends TestCase
{
    use DatabaseTransactions;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }
    }

    public function test_overview_lists_vertices_from_age_graph_connection(): void
    {
        $label = $this->graphLabel();
        $displayName = '測試 VTuber '.fake()->unique()->numerify('####');

        $vertexType = VertexType::factory()->create([
            'age_label_name' => $label,
            'overview_order' => 1,
            'show_property_name' => 'name_zh_tw',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '名稱',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->createAgeVertex($label, [
            'name_zh_tw' => $displayName,
        ]);

        $this->get(route('overview'))
            ->assertOk()
            ->assertSee($vertexType->name, false)
            ->assertSee($displayName, false);
    }

    public function test_overview_ignores_vertex_types_without_overview_order(): void
    {
        $hiddenLabel = $this->graphLabel();
        $visibleLabel = $this->graphLabel();
        $hiddenName = '隱藏類型 '.fake()->unique()->numerify('####');
        $visibleName = '可見類型 '.fake()->unique()->numerify('####');
        $hiddenVertexName = '隱藏頂點 '.fake()->unique()->numerify('####');
        $visibleVertexName = '可見頂點 '.fake()->unique()->numerify('####');

        $hiddenType = VertexType::factory()->create([
            'name' => $hiddenName,
            'age_label_name' => $hiddenLabel,
            'overview_order' => null,
            'show_property_name' => 'name_zh_tw',
        ]);
        VertexProperty::factory()->for($hiddenType)->create([
            'name' => '名稱',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $visibleType = VertexType::factory()->create([
            'name' => $visibleName,
            'age_label_name' => $visibleLabel,
            'overview_order' => 1,
            'show_property_name' => 'name_zh_tw',
        ]);
        VertexProperty::factory()->for($visibleType)->create([
            'name' => '名稱',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->createAgeVertex($hiddenLabel, ['name_zh_tw' => $hiddenVertexName]);
        $this->createAgeVertex($visibleLabel, ['name_zh_tw' => $visibleVertexName]);

        $this->get(route('overview'))
            ->assertOk()
            ->assertSee($visibleName, false)
            ->assertSee($visibleVertexName, false)
            ->assertDontSee($hiddenName, false)
            ->assertDontSee($hiddenVertexName, false);
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

    private function graphLabel(): string
    {
        return 'label_'.fake()->unique()->lexify('??????');
    }
}
