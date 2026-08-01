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

#[Name('update-revision')]
#[Description('Update draft revision title and/or description only. Does not modify actions — use *-revision-action tools for those.')]
class UpdateRevisionTool extends Tool
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $revision = Revision::query()->findOrFail($validated['revision_id']);

        if (! Gate::forUser($user)->allows('update', $revision)) {
            return Response::error('Not authorized to update this revision.');
        }

        if (! $revision->isDraft()) {
            return Response::error('Only draft revisions can be updated.');
        }

        try {
            $this->revisionService->updateMetadata($revision, $validated);
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
                ->description('Revision ID')
                ->required(),
            'title' => $schema->string()
                ->description('Revision title')
                ->required(),
            'description' => $schema->string()
                ->description('Revision description'),
        ];
    }
}
