<?php

namespace App\Support;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\EdgeTypeVertexPair;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
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
        $edgeType->loadMissing('vertexPairs.startVertex', 'vertexPairs.endVertex');

        if ($edgeType->vertexPairs->isEmpty()) {
            return $this->edgeLabelHasPropertyData($edgeType->age_label_name, $edgeProperty->age_property_name);
        }

        foreach ($edgeType->vertexPairs as $pair) {
            if ($this->pairHasPropertyData($edgeType, $pair, $edgeProperty->age_property_name)) {
                return true;
            }
        }

        return false;
    }

    public function edgeTypeHasData(EdgeType $edgeType): bool
    {
        $edgeType->loadMissing('vertexPairs.startVertex', 'vertexPairs.endVertex');

        if ($edgeType->vertexPairs->isEmpty()) {
            return $this->edgeLabelHasData($edgeType->age_label_name);
        }

        foreach ($edgeType->vertexPairs as $pair) {
            if ($this->pairHasData($edgeType, $pair)) {
                return true;
            }
        }

        return false;
    }

    public function pairHasData(EdgeType $edgeType, EdgeTypeVertexPair $pair): bool
    {
        $startLabel = $pair->startVertex?->age_label_name;
        $endLabel = $pair->endVertex?->age_label_name;

        if ($startLabel === null || $endLabel === null) {
            return false;
        }

        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $startLabel, $endLabel) {
                return $builder
                    ->matchNode('s', $startLabel)
                    ->withMatchEdge(Direction::RIGHT, 'e', $edgeType->age_label_name)
                    ->withMatchNode('t', $endLabel)
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    private function pairHasPropertyData(EdgeType $edgeType, EdgeTypeVertexPair $pair, string $propertyName): bool
    {
        $startLabel = $pair->startVertex?->age_label_name;
        $endLabel = $pair->endVertex?->age_label_name;

        if ($startLabel === null || $endLabel === null) {
            return false;
        }

        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $startLabel, $endLabel, $propertyName) {
                return $builder->matchRaw(
                    '(s:'.$startLabel.')-[e:'.$edgeType->age_label_name.']->(t:'.$endLabel.') WHERE e.'.$propertyName.' IS NOT NULL'
                )
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    private function edgeLabelHasData(string $edgeLabel): bool
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeLabel) {
                return $builder->matchNode()
                    ->withMatchEdge(Direction::BOTH, 'e', $edgeLabel)
                    ->withMatchNode()
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    private function edgeLabelHasPropertyData(string $edgeLabel, string $propertyName): bool
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeLabel, $propertyName) {
                return $builder->matchRaw('()-[e:'.$edgeLabel.']-() WHERE e.'.$propertyName.' IS NOT NULL')
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    /**
     * Whether any vertex of this type has the ENUM list member `$value`.
     */
    public function vertexPropertyEnumValueInUse(
        VertexType $vertexType,
        VertexProperty $vertexProperty,
        string $value,
    ): bool {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType, $vertexProperty, $value) {
                return $builder->matchRaw(
                    '(v:'.$vertexType->age_label_name.') WHERE $v1 IN v.'.$vertexProperty->age_property_name,
                    [$value],
                )
                    ->return('v')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    /**
     * Whether any edge of this type has the ENUM list member `$value`.
     */
    public function edgePropertyEnumValueInUse(
        EdgeType $edgeType,
        EdgeProperty $edgeProperty,
        string $value,
    ): bool {
        $edgeType->loadMissing('vertexPairs.startVertex', 'vertexPairs.endVertex');

        if ($edgeType->vertexPairs->isEmpty()) {
            return $this->edgeLabelHasEnumValue($edgeType->age_label_name, $edgeProperty->age_property_name, $value);
        }

        foreach ($edgeType->vertexPairs as $pair) {
            if ($this->pairHasEnumValue($edgeType, $pair, $edgeProperty->age_property_name, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $candidateValues
     * @return list<string>
     */
    public function usedVertexEnumValues(
        VertexType $vertexType,
        VertexProperty $vertexProperty,
        array $candidateValues,
    ): array {
        return array_values(array_filter(
            $candidateValues,
            fn (string $value): bool => $this->vertexPropertyEnumValueInUse($vertexType, $vertexProperty, $value),
        ));
    }

    /**
     * @param  list<string>  $candidateValues
     * @return list<string>
     */
    public function usedEdgeEnumValues(
        EdgeType $edgeType,
        EdgeProperty $edgeProperty,
        array $candidateValues,
    ): array {
        return array_values(array_filter(
            $candidateValues,
            fn (string $value): bool => $this->edgePropertyEnumValueInUse($edgeType, $edgeProperty, $value),
        ));
    }

    private function pairHasEnumValue(
        EdgeType $edgeType,
        EdgeTypeVertexPair $pair,
        string $propertyName,
        string $value,
    ): bool {
        $startLabel = $pair->startVertex?->age_label_name;
        $endLabel = $pair->endVertex?->age_label_name;

        if ($startLabel === null || $endLabel === null) {
            return false;
        }

        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $startLabel, $endLabel, $propertyName, $value) {
                return $builder->matchRaw(
                    '(s:'.$startLabel.')-[e:'.$edgeType->age_label_name.']->(t:'.$endLabel.') WHERE $v1 IN e.'.$propertyName,
                    [$value],
                )
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }

    private function edgeLabelHasEnumValue(string $edgeLabel, string $propertyName, string $value): bool
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeLabel, $propertyName, $value) {
                return $builder->matchRaw(
                    '()-[e:'.$edgeLabel.']-() WHERE $v1 IN e.'.$propertyName,
                    [$value],
                )
                    ->return('e')
                    ->limit(1);
            })->get()->isNotEmpty();
    }
}
