<?php

namespace App\Mcp\Tools\Revision;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Mcp\Concerns\ProvidesRevisionActionSchema;
use App\Models\Revision;
use App\Models\RevisionAction;
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

#[Name('update-revision-action')]
#[Description('更新單筆 revision action 內容（不含排序）。')]
class UpdateRevisionActionTool extends Tool
{
    use AuthenticatesMcpRequests;
    use ProvidesRevisionActionSchema;

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
            'action' => ['required', 'array'],
            'action.action' => ['required', 'string'],
            'action.target_age_id' => ['nullable', 'integer'],
            'action.target_ref_order' => ['nullable', 'integer'],
            'action.vertex_type_label' => ['nullable', 'string'],
            'action.edge_type_label' => ['nullable', 'string'],
            'action.start_vertex_age_id' => ['nullable', 'integer'],
            'action.start_vertex_ref_order' => ['nullable', 'integer'],
            'action.end_vertex_age_id' => ['nullable', 'integer'],
            'action.end_vertex_ref_order' => ['nullable', 'integer'],
            'action.age_property_name' => ['nullable', 'string'],
            'action.value' => ['nullable'],
        ]);

        $revision = Revision::query()->findOrFail($validated['revision_id']);
        $action = RevisionAction::query()->findOrFail($validated['action_id']);

        if (! Gate::forUser($user)->allows('update', $revision)) {
            return Response::error('無權限更新此修訂。');
        }

        if (! $revision->isDraft()) {
            return Response::error('只有草稿狀態的修訂可以更新。');
        }

        try {
            $this->revisionService->updateAction($revision, $action, $validated['action']);
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
            'action_id' => $schema->integer()
                ->description('revision_actions.id')
                ->required(),
            'action' => $this->revisionActionSchema($schema)->required(),
        ];
    }
}
