<?php

namespace App\Http\Controllers;

use App\Models\VertexType;
use App\Support\VertexDisplayNameResolver;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct(
        private VertexDisplayNameResolver $displayNameResolver,
    ) {}

    public const AUTHENTICATED_REDIRECT = '/overview';

    public function index()
    {
        return view('index');
    }

    public function overview()
    {
        $vertexTypes = VertexType::whereNotNull('overview_order')
            ->with('properties')
            ->orderBy('overview_order')
            ->get();

        $vertexInfoList = [];
        /** @var VertexType $vertexType */
        foreach ($vertexTypes as $vertexType) {
            $vertexList = DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vertexType) {
                return $builder->matchNode('v', $vertexType->age_label_name)
                    ->return('v');
            })->get();

            $vertexList = $vertexList->map(function (object $item) use ($vertexType) {
                $item->displayName = $this->displayNameResolver->resolve(
                    $vertexType->show_property_name,
                    $this->normalizeAgeProperties($item->v->properties ?? []),
                    $vertexType->properties,
                );

                return $item;
            });

            $vertexInfoList[] = [
                'type' => $vertexType,
                'vertexList' => $vertexList,
            ];
        }

        return view('overview', compact('vertexInfoList'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAgeProperties(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }

        if (is_object($properties)) {
            return (array) $properties;
        }

        return [];
    }
}
