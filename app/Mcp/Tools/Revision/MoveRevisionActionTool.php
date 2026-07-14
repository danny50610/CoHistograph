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
#[Description('移動 revision action 至新位置（to_order 或 direction）。')]
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
            return Response::error('to_order 與 direction 必須二擇一。');
        }

        if (array_key_exists('to_order', $validated) && $validated['to_order'] !== null && ! empty($validated['direction'])) {
            return Response::error('to_order 與 direction 必須二擇一。');
        }

        $revision = Revision::query()->findOrFail($validated['revision_id']);
        $action = RevisionAction::query()->findOrFail($validated['action_id']);

        if (! Gate::forUser($user)->allows('update', $revision)) {
            return Response::error('無權限更新此修訂。');
        }

        if (! $revision->isDraft()) {
            return Response::error('只有草稿狀態的修訂可以更新。');
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
                ->description('修訂 ID')
                ->required(),
            'action_id' => $schema->integer()
                ->description('revision_actions.id')
                ->required(),
            'to_order' => $schema->integer()
                ->description('目標位置（0-based）；與 direction 二擇一'),
            'direction' => $schema->string()
                ->enum(['up', 'down'])
                ->description('與相鄰 action 交換；與 to_order 二擇一'),
        ];
    }
}
