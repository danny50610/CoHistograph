<?php

namespace App\Mcp\Tools\Schema;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Models\EdgeType;
use App\Models\EdgeTypeVertexPair;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-edge-types')]
#[Description('Search EdgeTypes (optionally include properties and endpoint VertexType pairs).')]
#[IsReadOnly]
class SearchEdgeTypesTool extends Tool
{
    use AuthenticatesMcpRequests;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->authenticatedUser($request);
        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'min:1', 'max:100'],
            'start_vertex_type_label' => ['nullable', 'string', 'max:255'],
            'end_vertex_type_label' => ['nullable', 'string', 'max:255'],
            'include_properties' => ['nullable', 'boolean'],
            'include_vertices' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = isset($validated['query']) ? trim($validated['query']) : null;
        $includeProperties = (bool) ($validated['include_properties'] ?? false);
        $includeVertices = (bool) ($validated['include_vertices'] ?? true);
        $limit = (int) ($validated['limit'] ?? 20);
        $offset = (int) ($validated['offset'] ?? 0);

        $with = [];
        if ($includeVertices) {
            $with[] = 'vertexPairs.startVertex';
            $with[] = 'vertexPairs.endVertex';
        }
        if ($includeProperties) {
            $with[] = 'properties';
        }

        $builder = EdgeType::query()
            ->withCount('properties')
            ->with($with)
            ->orderBy('id');

        if ($query !== null && $query !== '') {
            $like = '%'.mb_strtolower($query).'%';
            $builder->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(reverse_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(age_label_name) LIKE ?', [$like]);
            });
        }

        if (! empty($validated['start_vertex_type_label'])) {
            $builder->whereHas(
                'startVertices',
                fn ($q) => $q->where('age_label_name', $validated['start_vertex_type_label'])
            );
        }

        if (! empty($validated['end_vertex_type_label'])) {
            $builder->whereHas(
                'endVertices',
                fn ($q) => $q->where('age_label_name', $validated['end_vertex_type_label'])
            );
        }

        $total = (clone $builder)->count();
        $edgeTypes = $builder->skip($offset)->take($limit)->get()
            ->map(function (EdgeType $edgeType) use ($includeProperties, $includeVertices): array {
                $payload = [
                    'id' => $edgeType->id,
                    'name' => $edgeType->name,
                    'reverse_name' => $edgeType->reverse_name,
                    'age_label_name' => $edgeType->age_label_name,
                    'description' => $edgeType->description,
                    'properties_count' => $edgeType->properties_count,
                ];

                if ($includeVertices) {
                    $payload['vertex_pairs'] = $edgeType->vertexPairs
                        ->map(function (EdgeTypeVertexPair $pair): array {
                            return [
                                'start_vertex' => $pair->startVertex === null ? null : [
                                    'name' => $pair->startVertex->name,
                                    'age_label_name' => $pair->startVertex->age_label_name,
                                ],
                                'end_vertex' => $pair->endVertex === null ? null : [
                                    'name' => $pair->endVertex->name,
                                    'age_label_name' => $pair->endVertex->age_label_name,
                                ],
                            ];
                        })
                        ->values()
                        ->all();
                }

                if ($includeProperties) {
                    $payload['properties'] = $edgeType->properties->map(fn ($property) => [
                        'id' => $property->id,
                        'name' => $property->name,
                        'age_property_name' => $property->age_property_name,
                        'age_property_type' => $property->age_property_type->value,
                    ])->values()->all();
                }

                return $payload;
            })
            ->all();

        return Response::structured([
            'query' => $query,
            'total' => $total,
            'edge_types' => $edgeTypes,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search keyword; matches name, reverse_name, description, age_label_name'),
            'start_vertex_type_label' => $schema->string()
                ->description('Filter by start VertexType age_label_name (matches any allowed endpoint pair)'),
            'end_vertex_type_label' => $schema->string()
                ->description('Filter by end VertexType age_label_name (matches any allowed endpoint pair)'),
            'include_properties' => $schema->boolean()
                ->description('Include properties; default false')
                ->default(false),
            'include_vertices' => $schema->boolean()
                ->description('Include vertex_pairs (endpoint combinations) summary; default true')
                ->default(true),
            'limit' => $schema->integer()
                ->description('Default 20, max 50')
                ->default(20),
            'offset' => $schema->integer()
                ->description('Pagination offset')
                ->default(0),
        ];
    }
}
