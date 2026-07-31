<?php

namespace App\Mcp\Tools\Graph;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Services\GraphQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-edge-detail')]
#[Description('Get a single edge with properties and endpoint vertex summaries.')]
#[IsReadOnly]
class GetEdgeDetailTool extends Tool
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
            'age_id' => ['required', 'integer', 'min:0'],
        ]);

        $edge = $this->graphQueryService->getEdge((int) $validated['age_id']);
        if ($edge === null) {
            return Response::error("Edge not found: {$validated['age_id']}");
        }

        return Response::structured($edge);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'age_id' => $schema->integer()
                ->description('AGE edge ID')
                ->required(),
        ];
    }
}
