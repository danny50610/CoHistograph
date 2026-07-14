<?php

namespace App\Mcp\Tools\Revision;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Models\Revision;
use App\Services\RevisionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Name('validate-revision')]
#[Description('在不修改內容的情況下重新驗證草稿，並寫回驗證快取。')]
class ValidateRevisionTool extends Tool
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
            'revision_id' => ['required', 'integer', 'exists:revisions,id'],
        ]);

        $revision = Revision::query()->findOrFail($validated['revision_id']);

        if (! Gate::forUser($user)->allows('view', $revision)) {
            return Response::error('無權限查看此修訂。');
        }

        if (! $revision->isDraft()) {
            return Response::structured($this->revisionService->toChangeResponse($revision));
        }

        try {
            $this->revisionService->validateAndCache($revision);
        } catch (HttpException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured($this->revisionService->toChangeResponse($revision));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'revision_id' => $schema->integer()
                ->description('修訂 ID')
                ->required(),
        ];
    }
}
