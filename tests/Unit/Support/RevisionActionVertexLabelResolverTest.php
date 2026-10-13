<?php

namespace Tests\Unit\Support;

use App\Enums\RevisionActionType;
use App\Models\RevisionAction;
use App\Services\Graph\GraphEntitySearchService;
use App\Support\RevisionActionVertexLabelResolver;
use Mockery;
use Tests\TestCase;

class RevisionActionVertexLabelResolverTest extends TestCase
{
    public function test_resolves_create_edge_endpoint_display_names(): void
    {
        /** @var GraphEntitySearchService&Mockery\MockInterface $search */
        $search = Mockery::mock(GraphEntitySearchService::class);
        /** @var Mockery\Expectation $startExpectation */
        $startExpectation = $search->shouldReceive('findVertex');
        $startExpectation->once()->with(101)->andReturn([
            'id' => '101',
            'display_name' => '李白',
            'type_label' => 'person',
            'type_name' => '人物',
        ]);
        /** @var Mockery\Expectation $endExpectation */
        $endExpectation = $search->shouldReceive('findVertex');
        $endExpectation->once()->with(202)->andReturn([
            'id' => '202',
            'display_name' => '曲江宴會',
            'type_label' => 'event',
            'type_name' => '事件',
        ]);

        $resolver = new RevisionActionVertexLabelResolver($search);

        $actions = collect([
            new RevisionAction([
                'action' => RevisionActionType::CreateEdge,
                'start_vertex_age_id' => '101',
                'end_vertex_age_id' => '202',
            ]),
            new RevisionAction([
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => 'person',
            ]),
        ]);

        $this->assertSame([
            '101' => '李白',
            '202' => '曲江宴會',
        ], $resolver->labelsForActions($actions));
    }

    public function test_falls_back_to_id_when_vertex_missing_or_nameless(): void
    {
        /** @var GraphEntitySearchService&Mockery\MockInterface $search */
        $search = Mockery::mock(GraphEntitySearchService::class);
        /** @var Mockery\Expectation $missingExpectation */
        $missingExpectation = $search->shouldReceive('findVertex');
        $missingExpectation->once()->with(1)->andReturn(null);
        /** @var Mockery\Expectation $namelessExpectation */
        $namelessExpectation = $search->shouldReceive('findVertex');
        $namelessExpectation->once()->with(2)->andReturn([
            'id' => '2',
            'display_name' => '(ID: 2)',
            'type_label' => 'person',
            'type_name' => '人物',
        ]);

        $resolver = new RevisionActionVertexLabelResolver($search);

        $this->assertSame('ID:1', $resolver->labelForAgeId('1'));
        $this->assertSame('ID:2', $resolver->labelForAgeId('2'));
    }

    public function test_deduplicates_vertex_lookups_across_actions(): void
    {
        /** @var GraphEntitySearchService&Mockery\MockInterface $search */
        $search = Mockery::mock(GraphEntitySearchService::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $search->shouldReceive('findVertex');
        $expectation->once()->with(55)->andReturn([
            'id' => '55',
            'display_name' => '杜甫',
            'type_label' => 'person',
            'type_name' => '人物',
        ]);

        $resolver = new RevisionActionVertexLabelResolver($search);

        $actions = collect([
            new RevisionAction([
                'action' => RevisionActionType::CreateEdge,
                'start_vertex_age_id' => '55',
                'end_vertex_ref_order' => 0,
            ]),
            new RevisionAction([
                'action' => RevisionActionType::CreateEdge,
                'start_vertex_age_id' => '55',
                'end_vertex_age_id' => '55',
            ]),
        ]);

        $this->assertSame(['55' => '杜甫'], $resolver->labelsForActions($actions));
    }
}
