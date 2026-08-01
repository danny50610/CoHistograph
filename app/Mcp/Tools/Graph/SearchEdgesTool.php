<?php

namespace App\Mcp\Tools\Graph;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Services\GraphQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-edges')]
#[Description('Search edges by EdgeType, endpoints, or text. Use to obtain target_age_id for delete_edge or edge property actions.')]
#[IsReadOnly]
class SearchEdgesTool extends Tool
{
    use AuthenticatesMcpRequests;

    public function __construct(private GraphQueryService $graphQueryService) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->authenticatedUser($request);
        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'edge_type_label' => ['nullable', 'string', 'max:255'],
            'start_vertex_age_id' => ['nullable', 'integer', 'min:0'],
            'end_vertex_age_id' => ['nullable', 'integer', 'min:0'],
            'query' => ['nullable', 'string', 'min:1', 'max:100'],
            'property' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $this->graphQueryService->searchEdges(
                $validated['edge_type_label'] ?? null,
                isset($validated['start_vertex_age_id']) ? (int) $validated['start_vertex_age_id'] : null,
                isset($validated['end_vertex_age_id']) ? (int) $validated['end_vertex_age_id'] : null,
                isset($validated['query']) ? trim($validated['query']) : null,
                $validated['property'] ?? null,
                (int) ($validated['limit'] ?? 20),
                (int) ($validated['offset'] ?? 0),
            );
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured($result);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'edge_type_label' => $schema->string()
                ->description('AGE edge label; omit to search across EdgeTypes'),
            'start_vertex_age_id' => $schema->integer()
                ->description('Filter by start vertex AGE ID'),
            'end_vertex_age_id' => $schema->integer()
                ->description('Filter by end vertex AGE ID'),
            'query' => $schema->string()
                ->description('Text search keyword; when set, searches STRING properties on edges'),
            'property' => $schema->string()
                ->description('Limit search to this edge age_property_name'),
            'limit' => $schema->integer()
                ->description('Default 20, max 100')
                ->default(20),
            'offset' => $schema->integer()
                ->description('Pagination offset')
                ->default(0),
        ];
    }
}
