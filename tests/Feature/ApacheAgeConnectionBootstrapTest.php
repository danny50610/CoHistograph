<?php

namespace Tests\Feature;

use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApacheAgeConnectionBootstrapTest extends TestCase
{
    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');
    }

    public function test_graph_connection_search_path_includes_ag_catalog_after_connect(): void
    {
        DB::purge($this->graphConnection);

        $searchPath = DB::connection($this->graphConnection)
            ->selectOne('SHOW search_path');

        $this->assertNotNull($searchPath);
        $this->assertStringContainsString('ag_catalog', (string) $searchPath->search_path);
    }

    public function test_first_cypher_query_on_fresh_graph_connection_succeeds(): void
    {
        DB::purge($this->graphConnection);

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }

        // Purge again so the next cypher() is the first query on a brand-new session.
        DB::purge($this->graphConnection);

        $rows = DB::connection($this->graphConnection)
            ->apacheAgeCypher($this->graphName, function (Builder $builder) {
                return $builder->matchNode('v')->return('v');
            })
            ->limit(1)
            ->get();

        $this->assertNotNull($rows);
    }

    public function test_revision_apply_style_create_on_fresh_connection_succeeds(): void
    {
        DB::purge($this->graphConnection);

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }

        DB::purge($this->graphConnection);

        $label = 'label_'.fake()->unique()->lexify('??????');

        $result = DB::connection($this->graphConnection)
            ->transaction(function () use ($label) {
                return DB::connection($this->graphConnection)
                    ->apacheAgeCypher($this->graphName, function (Builder $builder) use ($label) {
                        return $builder->createNode('v', $label)->return('v');
                    })
                    ->first();
            });

        $this->assertNotNull($result?->v?->id);
    }
}
