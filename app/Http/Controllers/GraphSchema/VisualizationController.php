<?php

namespace App\Http\Controllers\GraphSchema;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Inertia\Inertia;
use Inertia\Response;

class VisualizationController extends Controller
{
    public function index(): Response
    {
        $vertexTypeList = VertexType::with('properties')->orderBy('id')->get()->map(fn (VertexType $vt) => [
            'id' => $vt->id,
            'name' => $vt->name,
            'age_label_name' => $vt->age_label_name,
            'description' => $vt->description,
            'properties' => $vt->properties->map(fn (VertexProperty $p) => $this->serializeProperty($p))->values()->all(),
            'url' => route('graph-schema.vertex-type.show', $vt),
        ]);

        $edgeTypeList = [];
        $edgeTypes = EdgeType::with(['properties', 'vertexPairs.startVertex', 'vertexPairs.endVertex'])
            ->orderBy('id')
            ->get();

        foreach ($edgeTypes as $et) {
            foreach ($et->vertexPairs as $pair) {
                $edgeTypeList[] = [
                    'id' => $et->id.'-'.$pair->id,
                    'edge_type_id' => $et->id,
                    'name' => $et->name,
                    'reverse_name' => $et->reverse_name,
                    'age_label_name' => $et->age_label_name,
                    'description' => $et->description,
                    'start_vertex_id' => $pair->start_vertex_id,
                    'end_vertex_id' => $pair->end_vertex_id,
                    'start_vertex_name' => $pair->startVertex?->name,
                    'end_vertex_name' => $pair->endVertex?->name,
                    'properties' => $et->properties->map(fn (EdgeProperty $p) => $this->serializeProperty($p))->values()->all(),
                    'url' => route('graph-schema.edge-type.show', $et),
                ];
            }
        }

        return Inertia::render('GraphSchema/Visualization', [
            'vertexTypeList' => $vertexTypeList,
            'edgeTypeList' => $edgeTypeList,
            'routeVertexTypeIndex' => route('graph-schema.vertex-type.index'),
            'routeEdgeTypeIndex' => route('graph-schema.edge-type.index'),
            'routeVisualization' => route('graph-schema.visualization'),
        ]);
    }

    /**
     * @return array{name: string, age_property_name: string, age_property_type: string, enum_options?: list<array{value: string, label: string, active: bool}>}
     */
    private function serializeProperty(VertexProperty|EdgeProperty $property): array
    {
        $payload = [
            'name' => $property->name,
            'age_property_name' => $property->age_property_name,
            'age_property_type' => $property->age_property_type->value,
        ];

        if ($property->age_property_type === PropertyType::Enum) {
            $payload['enum_options'] = is_array($property->enum_options) ? $property->enum_options : [];
        }

        return $payload;
    }
}
