<?php

namespace App\Services;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\RevisionAction;
use App\Models\User;
use App\Services\Revision\RevisionValidationResult;
use App\Services\Revision\RevisionValidationService;
use Illuminate\Support\Facades\DB;

class RevisionService
{
    public function __construct(private RevisionValidationService $revisionValidationService) {}

    public function create(User $user, array $data): Revision
    {
        return Revision::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);
    }

    public function update(Revision $revision, array $data): void
    {
        DB::transaction(function () use ($revision, $data) {
            $revision->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);

            $revision->actions()->delete();

            foreach ($data['actions'] as $index => $actionData) {
                $revision->actions()->create([
                    'order' => $index,
                    'action' => $actionData['action'],
                    'target_age_id' => $actionData['target_age_id'] ?? null,
                    'target_ref_order' => $actionData['target_ref_order'] ?? null,
                    'vertex_type_label' => $actionData['vertex_type_label'] ?? null,
                    'edge_type_label' => $actionData['edge_type_label'] ?? null,
                    'start_vertex_age_id' => $actionData['start_vertex_age_id'] ?? null,
                    'start_vertex_ref_order' => $actionData['start_vertex_ref_order'] ?? null,
                    'end_vertex_age_id' => $actionData['end_vertex_age_id'] ?? null,
                    'end_vertex_ref_order' => $actionData['end_vertex_ref_order'] ?? null,
                    'age_property_name' => $actionData['age_property_name'] ?? null,
                    'value' => $actionData['value'] ?? null,
                ]);
            }
        });
    }

    public function submit(Revision $revision): RevisionValidationResult
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以提交審核');

        $revision->load('actions');
        $validationResult = $this->revisionValidationService->validate($revision);
        if (! $validationResult->isValid()) {
            return $validationResult;
        }

        $revision->update(['status' => RevisionStatus::PendingReview]);

        return $validationResult;
    }

    public function validateDraftData(Revision $revision, array $data): RevisionValidationResult
    {
        $draftRevision = new Revision([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $revision->status,
            'user_id' => $revision->user_id,
        ]);

        $actions = collect($data['actions'])
            ->values()
            ->map(function (array $actionData, int $index) {
                return new RevisionAction([
                    'order' => $index,
                    'action' => $actionData['action'],
                    'target_age_id' => $actionData['target_age_id'] ?? null,
                    'target_ref_order' => $actionData['target_ref_order'] ?? null,
                    'vertex_type_label' => $actionData['vertex_type_label'] ?? null,
                    'edge_type_label' => $actionData['edge_type_label'] ?? null,
                    'start_vertex_age_id' => $actionData['start_vertex_age_id'] ?? null,
                    'start_vertex_ref_order' => $actionData['start_vertex_ref_order'] ?? null,
                    'end_vertex_age_id' => $actionData['end_vertex_age_id'] ?? null,
                    'end_vertex_ref_order' => $actionData['end_vertex_ref_order'] ?? null,
                    'age_property_name' => $actionData['age_property_name'] ?? null,
                    'value' => $actionData['value'] ?? null,
                ]);
            });

        $draftRevision->setRelation('actions', $actions);

        return $this->revisionValidationService->validate($draftRevision);
    }

    public function reopen(Revision $revision): void
    {
        abort_unless($revision->isRejected(), 422, '只有已退回的修訂可以重新開啟');

        $revision->update(['status' => RevisionStatus::Draft]);
    }

    public function destroy(Revision $revision): void
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以刪除');

        $revision->delete();
    }
}
