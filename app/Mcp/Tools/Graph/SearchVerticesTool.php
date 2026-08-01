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

#[Name('search-vertices')]
#[Description('List or text-search vertices by VertexType age_label_name. Use to obtain target_age_id before delete_vertex or vertex property actions. Omit query to paginate all of that type; set property to limit search to one STRING field.')]
#[IsReadOnly]
class SearchVerticesTool extends Tool
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
            'vertex_type_label' => ['required', 'string', 'max:255'],
            'query' => ['nullable', 'string', 'min:1', 'max:100'],
            'property' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $this->graphQueryService->searchVertices(
                $validated['vertex_type_label'],
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
            'vertex_type_label' => $schema->string()
                ->description('AGE label, e.g. person, event')
                ->required(),
            'query' => $schema->string()
                ->description('Text search keyword; when set, switches to search mode'),
            'property' => $schema->string()
                ->description('Limit search to this age_property_name; omit to search all STRING properties'),
            'limit' => $schema->integer()
                ->description('Default 20, max 100')
                ->default(20),
            'offset' => $schema->integer()
                ->description('Pagination offset')
                ->default(0),
        ];
    }
}
