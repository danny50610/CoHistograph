<?php

namespace App\Http\Controllers\Graph;

use App\Http\Controllers\Controller;
use App\Models\EdgeType;
use App\Models\VertexType;
use App\Support\LocalizedPropertyGrouper;
use App\Support\VertexDisplayNameResolver;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VertexController extends Controller
{
    public function __construct(
        private VertexDisplayNameResolver $displayNameResolver,
    ) {}

    public function index(Request $request)
    {
        $this->validate($request, [
            'type' => 'nullable|string|max:255',
        ]);

        $type = $request->input('type');

        if (is_null($type)) {
            $vertexTypeList = VertexType::orderBy('id')->paginate();

            $vertexInfoList = [];
            /** @var VertexType $vertexType */
            foreach ($vertexTypeList as $vertexType) {
                // TODO: 只取前幾個就好
                $vertexType->load('properties');

                $vertexList = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexType) {
                    return $builder->matchNode('v', $vertexType->age_label_name)
                        ->return('v');
                })->get();

                $vertexInfoList[] = [
                    'type' => $vertexType,
                    'vertexList' => $this->attachDisplayNames($vertexList, $vertexType),
                ];
            }

            return view('graph.vertex.all-type', compact('vertexTypeList', 'vertexInfoList'));
        } else {
            $vertexType = VertexType::where('age_label_name', $type)->with('properties')->first();
            if (is_null($vertexType)) {
                abort(404);
            }

            // TODO: paginate order by id
            $vertexList = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($type) {
                return $builder->matchNode('v', $type)
                    ->return('v');
            })->get();

            $vertexList = $this->attachDisplayNames($vertexList, $vertexType);

            return view('graph.vertex.index', compact('vertexType', 'vertexList'));
        }
    }

    public function show(int $id)
    {
        $vertex = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id) {
            return $builder->matchNode('v')
                ->where('id(v)', '=', $id)
                ->return('v');
        })->first();

        if (is_null($vertex)) {
            abort(404);
        }

        $vertex = $vertex->v;

        /** @var VertexType $vertexType */
        $vertexType = VertexType::where('age_label_name', $vertex->label)->with('properties')->firstOrFail();

        $vertexProperties = $this->normalizeAgeProperties($vertex->properties ?? []);

        $displayName = $this->displayNameResolver->resolve(
            $vertexType->show_property_name,
            $vertexProperties,
            $vertexType->properties,
        );

        $propertyGroups = app(LocalizedPropertyGrouper::class)->group(
            $vertexType->properties,
            $vertexProperties,
        );

        $edgeInfoList = $this->getVertexEdgeInfo($vertexType, $id);

        return view('graph.vertex.show', compact('vertex', 'vertexType', 'edgeInfoList', 'propertyGroups', 'displayName'));
    }

    protected function getVertexEdgeInfo(VertexType $vertexType, $id)
    {
        $edgeInfoList = [];

        $vertexType->load([
            'startEdgeTypes.properties',
            'startEdgeTypes.endVertex.properties',
            'endEdgeTypes.properties',
            'endEdgeTypes.startVertex.properties',
        ]);

        $this->mergeInfo($edgeInfoList, $vertexType, $id, $vertexType->startEdgeTypes, 'endVertex', Direction::RIGHT);
        $this->mergeInfo($edgeInfoList, $vertexType, $id, $vertexType->endEdgeTypes, 'startVertex', Direction::LEFT);

        foreach ($edgeInfoList as $edgeTypeId => $edgeInfo) {
            $relatedVertexType = $edgeInfo['vertex_type'];

            foreach ($edgeInfo['edges'] as $index => $edgeItem) {
                $edgeInfoList[$edgeTypeId]['edges'][$index]['displayName'] = $this->displayNameResolver->resolve(
                    $relatedVertexType->show_property_name,
                    $this->normalizeAgeProperties($edgeItem['vertex']->properties ?? []),
                    $relatedVertexType->properties,
                );
            }
        }

        return $edgeInfoList;
    }

    protected function mergeInfo(array &$edgeInfoList, VertexType $vertexType, $id, Collection $edgeTypeList, string $targetVertexName, Direction $direction)
    {
        /** @var EdgeType $edgeType */
        foreach ($edgeTypeList as $edgeType) {
            $edgeInfoList[$edgeType->id] = [
                'type' => $edgeType,
                'vertex_type' => $edgeType->{$targetVertexName},
                'edges' => [],
            ];
        }

        $edgeList = $this->graphConnection()->apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id, $vertexType, $direction) {
            return $builder->matchNode('v', $vertexType->age_label_name)
                ->withMatchEdge($direction, 'e')
                ->withMatchNode('m')
                ->where('id(v)', '=', $id)
                ->return(['e', 'm']);
        })->get();

        foreach ($edgeList as $item) {
            $edge = $item->e;
            $vertex = $item->m;

            foreach ($edgeInfoList as $edgeTypeId => $info) {
                if ($edge->label === $info['type']->age_label_name && $vertex->label === $info['vertex_type']->age_label_name) {
                    // TODO: 未來需要支援排序，例如歌曲的主唱順序
                    $edgeInfoList[$edgeTypeId]['edges'][] = [
                        'edge' => $edge,
                        'vertex' => $vertex,
                    ];
                    break;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeAgeProperties(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }

        if (is_object($properties)) {
            return (array) $properties;
        }

        return [];
    }

    /**
     * @param  Collection<int, object>  $vertexList
     * @return Collection<int, object>
     */
    protected function attachDisplayNames(Collection $vertexList, VertexType $vertexType): Collection
    {
        return $vertexList->map(function (object $item) use ($vertexType) {
            $item->displayName = $this->displayNameResolver->resolve(
                $vertexType->show_property_name,
                $this->normalizeAgeProperties($item->v->properties ?? []),
                $vertexType->properties,
            );

            return $item;
        });
    }

    protected function graphConnection()
    {
        return DB::connection(config('cohistograph.app.graph.connection-name'));
    }
}
