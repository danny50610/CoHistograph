<?php

namespace App\Mcp\Tools\Revision;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Models\Revision;
use App\Models\RevisionAction;
use App\Services\RevisionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Name('move-revision-action')]
#[Description('Reorder one draft action via to_order (0-based) or direction=up|down. Resequencing can break *_ref_order references — always validate-revision after moving.')]
class MoveRevisionActionTool extends Tool
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
            'action_id' => ['required', 'integer', 'exists:revision_actions,id'],
            'to_order' => ['nullable', 'integer', 'min:0'],
            'direction' => ['nullable', 'string', 'in:up,down'],
        ]);

        if ((! array_key_exists('to_order', $validated) || $validated['to_order'] === null)
            && empty($validated['direction'])) {
            return Response::error('Provide exactly one of to_order or direction.');
        }

        if (array_key_exists('to_order', $validated) && $validated['to_order'] !== null && ! empty($validated['direction'])) {
            return Response::error('Provide exactly one of to_order or direction.');
        }

        $revision = Revision::query()->findOrFail($validated['revision_id']);
        $action = RevisionAction::query()->findOrFail($validated['action_id']);

        if (! Gate::forUser($user)->allows('update', $revision)) {
            return Response::error('Not authorized to update this revision.');
        }

        if (! $revision->isDraft()) {
            return Response::error('Only draft revisions can be updated.');
        }

        try {
            $this->revisionService->moveAction(
                $revision,
                $action,
                isset($validated['to_order']) ? (int) $validated['to_order'] : null,
                $validated['direction'] ?? null,
            );
        } catch (InvalidArgumentException|HttpException $exception) {
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
            'action_id' => $schema->integer()
                ->description('revision_actions.id')
                ->required(),
            'to_order' => $schema->integer()
                ->description('Target position (0-based); mutually exclusive with direction'),
            'direction' => $schema->string()
                ->enum(['up', 'down'])
                ->description('Swap with adjacent action; mutually exclusive with to_order'),
        ];
    }
}
