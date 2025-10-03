<?php

namespace App\Http\Controllers\Graph;

use App\Http\Controllers\Controller;
use App\Models\VertexType;
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
            // TODO: list all vertex types with counts and links to their pages
            return view('graph.vertex.all');
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

        $vertexType = VertexType::where('age_label_name', $vertex->label)->firstOrFail();

        return view('graph.vertex.show', compact('vertex', 'vertexType'));
    }
}
