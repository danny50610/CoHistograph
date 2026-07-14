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

#[Name('list-vertex-neighbors')]
#[Description('列出頂點的相鄰節點與邊。')]
#[IsReadOnly]
class ListVertexNeighborsTool extends Tool
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
            'direction' => ['nullable', 'string', 'in:outgoing,incoming,both'],
        ]);

        try {
            $result = $this->graphQueryService->listVertexNeighbors(
                (int) $validated['age_id'],
                $validated['direction'] ?? 'both',
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
            'age_id' => $schema->integer()
                ->description('AGE 頂點 ID')
                ->required(),
            'direction' => $schema->string()
                ->enum(['outgoing', 'incoming', 'both'])
                ->description('outgoing / incoming / both，預設 both')
                ->default('both'),
        ];
    }
}
