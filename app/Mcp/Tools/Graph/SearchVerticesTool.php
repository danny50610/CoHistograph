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
#[Description('依 VertexType 列出頂點，或以文字搜尋頂點屬性，取得後續 action 所需的 target_age_id。')]
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
                ->description('AGE label，如 person、event')
                ->required(),
            'query' => $schema->string()
                ->description('文字搜尋關鍵字；有值時改為搜尋模式'),
            'property' => $schema->string()
                ->description('限定搜尋的 age_property_name；省略時搜尋所有 STRING 屬性'),
            'limit' => $schema->integer()
                ->description('預設 20，上限 100')
                ->default(20),
            'offset' => $schema->integer()
                ->description('分頁偏移')
                ->default(0),
        ];
    }
}
