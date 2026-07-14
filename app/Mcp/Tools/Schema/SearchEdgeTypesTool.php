<?php

namespace App\Mcp\Tools\Schema;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Models\EdgeType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-edge-types')]
#[Description('搜尋 EdgeType（可選含 Property、起訖 VertexType 與使用指南）。')]
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
        if ($includeVertices || isset($validated['start_vertex_type_label']) || isset($validated['end_vertex_type_label'])) {
            $with[] = 'startVertex';
            $with[] = 'endVertex';
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
                    ->orWhereRaw('LOWER(COALESCE(usage_guidelines, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(age_label_name) LIKE ?', [$like]);
            });
        }

        if (! empty($validated['start_vertex_type_label'])) {
            $builder->whereHas('startVertex', fn ($q) => $q->where('age_label_name', $validated['start_vertex_type_label']));
        }

        if (! empty($validated['end_vertex_type_label'])) {
            $builder->whereHas('endVertex', fn ($q) => $q->where('age_label_name', $validated['end_vertex_type_label']));
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
                    'usage_guidelines' => $edgeType->usage_guidelines,
                    'properties_count' => $edgeType->properties_count,
                ];

                if ($includeVertices) {
                    $payload['start_vertex'] = $edgeType->startVertex === null ? null : [
                        'name' => $edgeType->startVertex->name,
                        'age_label_name' => $edgeType->startVertex->age_label_name,
                    ];
                    $payload['end_vertex'] = $edgeType->endVertex === null ? null : [
                        'name' => $edgeType->endVertex->name,
                        'age_label_name' => $edgeType->endVertex->age_label_name,
                    ];
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
                ->description('搜尋關鍵字；比對 name、reverse_name、description、usage_guidelines、age_label_name'),
            'start_vertex_type_label' => $schema->string()
                ->description('篩選起點 VertexType 的 age_label_name'),
            'end_vertex_type_label' => $schema->string()
                ->description('篩選終點 VertexType 的 age_label_name'),
            'include_properties' => $schema->boolean()
                ->description('是否附帶 properties，預設 false')
                ->default(false),
            'include_vertices' => $schema->boolean()
                ->description('是否附帶 start_vertex、end_vertex 摘要，預設 true')
                ->default(true),
            'limit' => $schema->integer()
                ->description('預設 20，上限 50')
                ->default(20),
            'offset' => $schema->integer()
                ->description('分頁偏移')
                ->default(0),
        ];
    }
}
