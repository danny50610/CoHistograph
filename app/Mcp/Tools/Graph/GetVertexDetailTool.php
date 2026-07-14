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

#[Name('get-vertex-detail')]
#[Description('取得單一頂點詳情與屬性。')]
#[IsReadOnly]
class GetVertexDetailTool extends Tool
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

        $vertex = $this->graphQueryService->getVertex((int) $validated['age_id']);
        if ($vertex === null) {
            return Response::error("找不到頂點: {$validated['age_id']}");
        }

        return Response::structured($vertex);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'age_id' => $schema->integer()
                ->description('AGE 頂點 ID')
                ->required(),
        ];
    }
}
