<?php

namespace App\Http\Controllers;

use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public const AUTHENTICATED_REDIRECT = '/overview';

    public function index()
    {
        return view('index');
    }

    public function overview()
    {
        $vertexTypes = VertexType::whereNotNull('overview_order')->orderBy('overview_order')->get();

        $vertexInfoList = [];
        /** @var VertexType $vertexType */
        foreach ($vertexTypes as $vertexType) {
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

        return view('overview', compact('vertexInfoList'));
    }
}
