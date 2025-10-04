<?php

namespace App\Http\Controllers\Graph;

use App\Http\Controllers\Controller;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VertexController extends Controller
{
    public function index(Request $request)
    {
        $this->validate($request, [
            'type' => 'nullable|string|max:255',
        ]);

        if (is_null($request->type)) {
            $vertexTypeList = VertexType::orderBy('id')->paginate();

            $vertexInfoList = [];
            /** @var VertexType $vertexType */
            foreach ($vertexTypeList as $vertexType) {
                // TODO: 只取前幾個就好
                $vertexList = DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexType) {
                    return $builder->matchNode('v', $vertexType->age_label_name)
                        ->return('v');
                })->get();

                $vertexInfoList[] = [
                    'type' => $vertexType,
                    'vertexList' => $vertexList,
                ];
            }

            return view('graph.vertex.all-type', compact('vertexTypeList', 'vertexInfoList'));
        } else {
            $type = $request->type;
            $vertexType = VertexType::where('age_label_name', $type)->first();

            // TODO: paginate order by id
            $vertexList = DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($type) {
                return $builder->matchNode('v', $type)
                    ->return('v');
            })->get();

            return view('graph.vertex.index', compact('vertexType', 'vertexList'));
        }
    }

    public function show(int $id)
    {
        $vertex = DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id) {
            return $builder->matchNode('v')
                ->where('id(v)', '=', $id)
                ->return('v');
        })->first();

        if (is_null($vertex)) {
            abort(404);
        }

        $vertex = $vertex->v;

        /** @var VertexType $vertexType */
        $vertexType = VertexType::where('age_label_name', $vertex->label)->firstOrFail();

        $edgeInfoList = [];
        $startEdgeTypeList = $vertexType->startEdgeTypes;
        $endEdgeTypeList = $vertexType->endEdgeTypes;
        foreach ($startEdgeTypeList as $edgeType) {
            $edgeInfoList[$edgeType->age_label_name] = [
                'type' => $edgeType,
                'edges' => [],
            ];
        }
        foreach ($endEdgeTypeList as $edgeType) {
            $edgeInfoList[$edgeType->age_label_name] = [
                'type' => $edgeType,
                'edges' => [],
            ];
        }

        // TODO: 分成兩次查詢比較快，有機會合併 code
        $edgeList = DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id, $vertexType) {
            return $builder->matchNode('v', $vertexType->age_label_name)
                ->withMatchEdge(Direction::RIGHT, 'e')
                ->withMatchNode('m')
                ->where('id(v)', '=', $id)
                ->return('e')
                ->return('m');
        })->get();

        foreach ($edgeList as $item) {
            $edge = $item->e;

            $edgeInfoList[$edge->label]['edges'][] = [
                'edge' => $edge,
                'end_vertex' => $item->m,
            ];
        }

        $edgeList = DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id, $vertexType) {
            return $builder->matchNode('v', $vertexType->age_label_name)
                ->withMatchEdge(Direction::LEFT, 'e')
                ->withMatchNode('m')
                ->where('id(v)', '=', $id)
                ->return('e')
                ->return('m');
        })->get();

        foreach ($edgeList as $item) {
            $edge = $item->e;

            $edgeInfoList[$edge->label]['edges'][] = [
                'edge' => $edge,
                'end_vertex' => $item->m,
            ];
        }

        return view('graph.vertex.show', compact('vertex', 'vertexType', 'edgeInfoList'));
    }
}
