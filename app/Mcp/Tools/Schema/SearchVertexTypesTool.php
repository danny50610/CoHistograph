<?php

namespace App\Mcp\Tools\Schema;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Models\VertexType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-vertex-types')]
#[Description('搜尋 VertexType（可選含 Property 定義與使用指南）。')]
#[IsReadOnly]
class SearchVertexTypesTool extends Tool
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
            'include_properties' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = isset($validated['query']) ? trim($validated['query']) : null;
        $includeProperties = (bool) ($validated['include_properties'] ?? false);
        $limit = (int) ($validated['limit'] ?? 20);
        $offset = (int) ($validated['offset'] ?? 0);

        $builder = VertexType::query()->withCount('properties')->orderBy('id');

        if ($includeProperties) {
            $builder->with('properties');
        }

        if ($query !== null && $query !== '') {
            $like = '%'.mb_strtolower($query).'%';
            $builder->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(usage_guidelines, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(age_label_name) LIKE ?', [$like]);
            });
        }

        $total = (clone $builder)->count();
        $vertexTypes = $builder->skip($offset)->take($limit)->get()
            ->map(function (VertexType $vertexType) use ($includeProperties): array {
                $payload = [
                    'id' => $vertexType->id,
                    'name' => $vertexType->name,
                    'age_label_name' => $vertexType->age_label_name,
                    'description' => $vertexType->description,
                    'usage_guidelines' => $vertexType->usage_guidelines,
                    'properties_count' => $vertexType->properties_count,
                ];

                if ($includeProperties) {
                    $payload['properties'] = $vertexType->properties->map(fn ($property) => [
                        'id' => $property->id,
                        'name' => $property->name,
                        'age_property_name' => $property->age_property_name,
                        'age_property_type' => $property->age_property_type->value,
                        'description' => $property->description,
                    ])->values()->all();
                }

                return $payload;
            })
            ->all();

        return Response::structured([
            'query' => $query,
            'total' => $total,
            'vertex_types' => $vertexTypes,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('搜尋關鍵字；比對 name、description、usage_guidelines、age_label_name'),
            'include_properties' => $schema->boolean()
                ->description('是否附帶 properties 陣列，預設 false')
                ->default(false),
            'limit' => $schema->integer()
                ->description('預設 20，上限 50')
                ->default(20),
            'offset' => $schema->integer()
                ->description('分頁偏移')
                ->default(0),
        ];
    }
}
