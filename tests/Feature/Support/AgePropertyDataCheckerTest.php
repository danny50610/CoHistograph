<?php

namespace Tests\Feature\Support;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\EdgeTypeVertexPair;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\AgePropertyDataChecker;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgePropertyDataCheckerTest extends TestCase
{
    use DatabaseTransactions;

    private AgePropertyDataChecker $checker;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = new AgePropertyDataChecker;
        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }
    }

    public function test_vertex_property_has_data_returns_false_when_empty(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'apdc_vp_empty_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'apdc_name',
        ]);

        $this->assertFalse($this->checker->vertexPropertyHasData($vertexType, $vertexProperty));
    }

    public function test_vertex_property_has_data_returns_true_when_property_set(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'apdc_vp_filled_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'apdc_name',
        ]);

        $this->createVertex($vertexType->age_label_name, [
            $vertexProperty->age_property_name => 'in_use',
        ]);

        $this->assertTrue($this->checker->vertexPropertyHasData($vertexType, $vertexProperty));
    }

    public function test_edge_property_has_data_with_vertex_pairs(): void
    {
        [$edgeType, $edgeProperty, $start, $end] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_ep_pair_et',
            startLabel: 'apdc_ep_pair_start',
            endLabel: 'apdc_ep_pair_end',
            propertyName: 'apdc_role',
        );

        $this->assertFalse($this->checker->edgePropertyHasData($edgeType, $edgeProperty));

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
            [$edgeProperty->age_property_name => 'lead'],
        );

        $this->assertTrue($this->checker->edgePropertyHasData($edgeType->fresh(), $edgeProperty));
    }

    public function test_edge_property_has_data_without_vertex_pairs(): void
    {
        [$edgeType, $edgeProperty] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_ep_nopair_et',
            startLabel: 'apdc_ep_nopair_start',
            endLabel: 'apdc_ep_nopair_end',
            propertyName: 'apdc_role',
        );

        $edgeType->vertexPairs()->delete();
        $edgeType->unsetRelation('vertexPairs');

        $this->assertFalse($this->checker->edgePropertyHasData($edgeType->fresh(), $edgeProperty));

        $this->createEdgeWithProperty(
            'apdc_ep_nopair_start',
            'apdc_ep_nopair_end',
            $edgeType->age_label_name,
            [$edgeProperty->age_property_name => 'lead'],
        );

        $this->assertTrue($this->checker->edgePropertyHasData($edgeType->fresh(), $edgeProperty));
    }

    public function test_edge_property_has_data_returns_false_when_pair_labels_missing(): void
    {
        [$edgeType, $edgeProperty] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_ep_nullpair_et',
            startLabel: 'apdc_ep_nullpair_start',
            endLabel: 'apdc_ep_nullpair_end',
            propertyName: 'apdc_role',
        );

        $this->nullifyPairVertexRelations($edgeType);

        $this->assertFalse($this->checker->edgePropertyHasData($edgeType, $edgeProperty));
    }

    public function test_edge_type_has_data_with_vertex_pairs(): void
    {
        $start = VertexType::factory()->create(['age_label_name' => 'apdc_et_pair_start']);
        $end = VertexType::factory()->create(['age_label_name' => 'apdc_et_pair_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'apdc_et_pair_et',
            'vertex_pairs' => [['start_vertex_id' => $start->id, 'end_vertex_id' => $end->id]],
        ]);

        $this->assertFalse($this->checker->edgeTypeHasData($edgeType));

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
        );

        $this->assertTrue($this->checker->edgeTypeHasData($edgeType->fresh()));
    }

    public function test_edge_type_has_data_without_vertex_pairs(): void
    {
        $start = VertexType::factory()->create(['age_label_name' => 'apdc_et_nopair_start']);
        $end = VertexType::factory()->create(['age_label_name' => 'apdc_et_nopair_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'apdc_et_nopair_et',
            'vertex_pairs' => [['start_vertex_id' => $start->id, 'end_vertex_id' => $end->id]],
        ]);

        $edgeType->vertexPairs()->delete();
        $edgeType->unsetRelation('vertexPairs');

        $this->assertFalse($this->checker->edgeTypeHasData($edgeType->fresh()));

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
        );

        $this->assertTrue($this->checker->edgeTypeHasData($edgeType->fresh()));
    }

    public function test_edge_type_has_data_returns_false_when_pair_labels_missing(): void
    {
        $start = VertexType::factory()->create(['age_label_name' => 'apdc_et_null_start']);
        $end = VertexType::factory()->create(['age_label_name' => 'apdc_et_null_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'apdc_et_null_et',
            'vertex_pairs' => [['start_vertex_id' => $start->id, 'end_vertex_id' => $end->id]],
        ]);

        $this->nullifyPairVertexRelations($edgeType);

        $this->assertFalse($this->checker->edgeTypeHasData($edgeType));
    }

    public function test_pair_has_data(): void
    {
        $start = VertexType::factory()->create(['age_label_name' => 'apdc_pair_start']);
        $end = VertexType::factory()->create(['age_label_name' => 'apdc_pair_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'apdc_pair_et',
            'vertex_pairs' => [['start_vertex_id' => $start->id, 'end_vertex_id' => $end->id]],
        ]);
        $pair = $edgeType->vertexPairs->first();

        $this->assertFalse($this->checker->pairHasData($edgeType, $pair));

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
        );

        $this->assertTrue($this->checker->pairHasData($edgeType, $pair->fresh()->load(['startVertex', 'endVertex'])));
    }

    public function test_pair_has_data_returns_false_when_labels_missing(): void
    {
        $start = VertexType::factory()->create(['age_label_name' => 'apdc_pair_null_start']);
        $end = VertexType::factory()->create(['age_label_name' => 'apdc_pair_null_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'apdc_pair_null_et',
            'vertex_pairs' => [['start_vertex_id' => $start->id, 'end_vertex_id' => $end->id]],
        ]);
        $pair = $edgeType->vertexPairs->first();
        $pair->setRelation('startVertex', null);
        $pair->setRelation('endVertex', null);

        $this->assertFalse($this->checker->pairHasData($edgeType, $pair));
    }

    public function test_vertex_property_enum_value_in_use(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'apdc_enum_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
        ])->create([
            'age_property_name' => 'apdc_genres',
        ]);

        $this->assertFalse(
            $this->checker->vertexPropertyEnumValueInUse($vertexType, $vertexProperty, 'rock')
        );

        $this->createVertex($vertexType->age_label_name, [
            $vertexProperty->age_property_name => ['rock', 'jazz'],
        ]);

        $this->assertTrue(
            $this->checker->vertexPropertyEnumValueInUse($vertexType, $vertexProperty, 'rock')
        );
        $this->assertFalse(
            $this->checker->vertexPropertyEnumValueInUse($vertexType, $vertexProperty, 'pop')
        );
    }

    public function test_edge_property_enum_value_in_use_with_vertex_pairs(): void
    {
        [$edgeType, $edgeProperty, $start, $end] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_enum_pair_et',
            startLabel: 'apdc_enum_pair_start',
            endLabel: 'apdc_enum_pair_end',
            propertyName: 'apdc_tags',
            enum: true,
        );

        $this->assertFalse(
            $this->checker->edgePropertyEnumValueInUse($edgeType, $edgeProperty, 'rock')
        );

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
            [$edgeProperty->age_property_name => ['rock']],
        );

        $this->assertTrue(
            $this->checker->edgePropertyEnumValueInUse($edgeType->fresh(), $edgeProperty, 'rock')
        );
        $this->assertFalse(
            $this->checker->edgePropertyEnumValueInUse($edgeType->fresh(), $edgeProperty, 'jazz')
        );
    }

    public function test_edge_property_enum_value_in_use_without_vertex_pairs(): void
    {
        [$edgeType, $edgeProperty, $start, $end] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_enum_nopair_et',
            startLabel: 'apdc_enum_nopair_start',
            endLabel: 'apdc_enum_nopair_end',
            propertyName: 'apdc_tags',
            enum: true,
        );

        $edgeType->vertexPairs()->delete();
        $edgeType->unsetRelation('vertexPairs');

        $this->assertFalse(
            $this->checker->edgePropertyEnumValueInUse($edgeType->fresh(), $edgeProperty, 'rock')
        );

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
            [$edgeProperty->age_property_name => ['rock']],
        );

        $this->assertTrue(
            $this->checker->edgePropertyEnumValueInUse($edgeType->fresh(), $edgeProperty, 'rock')
        );
    }

    public function test_edge_property_enum_value_in_use_returns_false_when_pair_labels_missing(): void
    {
        [$edgeType, $edgeProperty] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_enum_null_et',
            startLabel: 'apdc_enum_null_start',
            endLabel: 'apdc_enum_null_end',
            propertyName: 'apdc_tags',
            enum: true,
        );

        $this->nullifyPairVertexRelations($edgeType);

        $this->assertFalse(
            $this->checker->edgePropertyEnumValueInUse($edgeType, $edgeProperty, 'rock')
        );
    }

    public function test_used_vertex_enum_values_filters_candidates(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'apdc_used_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ['value' => 'pop', 'label' => '流行', 'active' => true],
        ])->create([
            'age_property_name' => 'apdc_used_genres',
        ]);

        $this->createVertex($vertexType->age_label_name, [
            $vertexProperty->age_property_name => ['rock', 'pop'],
        ]);

        $this->assertSame(
            ['rock', 'pop'],
            $this->checker->usedVertexEnumValues($vertexType, $vertexProperty, ['rock', 'jazz', 'pop']),
        );
    }

    public function test_used_edge_enum_values_filters_candidates(): void
    {
        [$edgeType, $edgeProperty, $start, $end] = $this->makeEdgeTypeWithProperty(
            edgeLabel: 'apdc_used_edge_et',
            startLabel: 'apdc_used_edge_start',
            endLabel: 'apdc_used_edge_end',
            propertyName: 'apdc_used_tags',
            enum: true,
        );

        $this->createEdgeWithProperty(
            $start->age_label_name,
            $end->age_label_name,
            $edgeType->age_label_name,
            [$edgeProperty->age_property_name => ['jazz']],
        );

        $this->assertSame(
            ['jazz'],
            $this->checker->usedEdgeEnumValues($edgeType->fresh(), $edgeProperty, ['rock', 'jazz', 'pop']),
        );
    }

    /**
     * @return array{0: EdgeType, 1: EdgeProperty, 2: VertexType, 3: VertexType}
     */
    private function makeEdgeTypeWithProperty(
        string $edgeLabel,
        string $startLabel,
        string $endLabel,
        string $propertyName,
        bool $enum = false,
    ): array {
        $start = VertexType::factory()->create(['age_label_name' => $startLabel]);
        $end = VertexType::factory()->create(['age_label_name' => $endLabel]);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => $edgeLabel,
            'vertex_pairs' => [['start_vertex_id' => $start->id, 'end_vertex_id' => $end->id]],
        ]);

        $factory = EdgeProperty::factory()->for($edgeType);
        if ($enum) {
            $factory = $factory->enum([
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => true],
                ['value' => 'pop', 'label' => '流行', 'active' => true],
            ]);
        }

        $edgeProperty = $factory->create([
            'age_property_name' => $propertyName,
        ]);

        return [$edgeType, $edgeProperty, $start, $end];
    }

    private function nullifyPairVertexRelations(EdgeType $edgeType): void
    {
        $edgeType->load('vertexPairs.startVertex', 'vertexPairs.endVertex');

        /** @var EdgeTypeVertexPair $pair */
        $pair = $edgeType->vertexPairs->first();
        $pair->setRelation('startVertex', null);
        $pair->setRelation('endVertex', null);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createVertex(string $label, array $properties = []): void
    {
        DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (AgeQueryBuilder $builder) use ($label, $properties) {
                return $builder->createNode(null, $label, $properties)->setAs(['v']);
            })->get();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function createEdgeWithProperty(
        string $startLabel,
        string $endLabel,
        string $edgeLabel,
        array $properties = [],
    ): void {
        DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (AgeQueryBuilder $builder) use ($startLabel, $endLabel, $edgeLabel, $properties) {
                return $builder->createNode('a', $startLabel)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeLabel, $properties)
                    ->withCreateNode('b', $endLabel)
                    ->setAs(['e']);
            })->get();
    }
}
