<?php

namespace App\Mcp\Tools\Revision;

use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Mcp\Concerns\ProvidesRevisionActionSchema;
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

#[Name('add-revision-action')]
#[Description('Insert one action into a draft at order (0-based); later actions shift down and *_ref_order values are remapped (incoming refs use pre-insert numbering). Look up schema/graph first. For new vertices: create_vertex then create_vertex_property. For ENUM properties, value must be a JSON array of option values (e.g. ["rock","jazz"]), not a scalar string. Use target_age_id XOR target_ref_order as documented in server instructions. Response includes refreshed validation.')]
class AddRevisionActionTool extends Tool
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
            'order' => ['required', 'integer', 'min:0'],
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

        if (! Gate::forUser($user)->allows('update', $revision)) {
            return Response::error('Not authorized to update this revision.');
        }

        if (! $revision->isDraft()) {
            return Response::error('Only draft revisions can be updated.');
        }

        try {
            $this->revisionService->addAction($revision, (int) $validated['order'], $validated['action']);
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
            'order' => $schema->integer()
                ->description('Insert position (0-based)')
                ->required(),
            'action' => $this->revisionActionSchema($schema)->required(),
        ];
    }
}
