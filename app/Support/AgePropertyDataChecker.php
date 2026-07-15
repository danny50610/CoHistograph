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
        $label = CypherIdentifier::quote($vertexType->age_label_name);
        $property = CypherIdentifier::quote($vertexProperty->age_property_name);

        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($label, $property) {
                return $builder->matchRaw('(v:'.$label.') WHERE v.'.$property.' IS NOT NULL')
                    ->return('v')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    public function edgePropertyHasData(EdgeType $edgeType, EdgeProperty $edgeProperty): bool
    {
        $label = CypherIdentifier::quote($edgeType->age_label_name);
        $property = CypherIdentifier::quote($edgeProperty->age_property_name);

        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($label, $property) {
                return $builder->matchRaw('()-[e:'.$label.']-() WHERE e.'.$property.' IS NOT NULL')
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }
}
