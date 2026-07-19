<?php

namespace App\Support;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Support\Facades\DB;

class AgePropertyDataChecker
{
    public function vertexPropertyHasData(VertexType $vertexType, VertexProperty $vertexProperty): bool
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType, $vertexProperty) {
                return $builder->matchRaw('(v:'.$vertexType->age_label_name.') WHERE v.'.$vertexProperty->age_property_name.' IS NOT NULL')
                    ->return('v')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    public function edgePropertyHasData(EdgeType $edgeType, EdgeProperty $edgeProperty): bool
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $edgeProperty) {
                return $builder->matchRaw('()-[e:'.$edgeType->age_label_name.']-() WHERE e.'.$edgeProperty->age_property_name.' IS NOT NULL')
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }
}
