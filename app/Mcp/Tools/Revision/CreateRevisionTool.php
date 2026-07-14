<?php

namespace App\Mcp\Tools\Revision;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Services\RevisionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-revision')]
#[Description('建立空白修訂草稿。')]
class CreateRevisionTool extends Tool
{
    use AuthenticatesMcpRequests;

    public function __construct(private RevisionService $revisionService) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->authenticatedUser($request);
        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $revision = $this->revisionService->create($user, $validated);
        $this->revisionService->refreshValidation($revision);

        return Response::structured($this->revisionService->toChangeResponse($revision));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('修訂標題')
                ->required(),
            'description' => $schema->string()
                ->description('修訂說明'),
        ];
    }
}
