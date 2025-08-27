<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApacheAgeService
{
    public function __construct(
        protected string $connectionName
    ) {
    }

    public function createGraph(string $graphName)
    {
        return DB::connection($this->connectionName)
            ->select("SELECT * FROM create_graph(?);", [$graphName]);
    }

    public function dropGraph(string $graphName, bool $cascade)
    {
        return DB::connection($this->connectionName
            )->select("SELECT * FROM drop_graph(?, ?);", [$graphName, $cascade]);
    }
}
