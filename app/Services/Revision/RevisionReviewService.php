<?php

namespace App\Services\Revision;

use App\Enums\RevisionReviewAction;
use App\Enums\RevisionStatus;
use App\Exceptions\CouldNotAcquireRevisionApplyLockException;
use App\Exceptions\RevisionApprovalValidationException;
use App\Models\Revision;
use App\Models\RevisionReview;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RevisionReviewService
{
    public function __construct(
        private RevisionValidationService $revisionValidationService,
        private RevisionApplyService $revisionApplyService,
    ) {}

    public function approve(Revision $revision, User $reviewer): void
    {
        abort_unless($revision->isPendingReview(), 422, '只有待審核的修訂可以接受');

        $lock = $this->acquireApplyLock();

        try {
            $revision->load([
                'actions' => fn ($query) => $query->orderBy('order'),
            ]);

            $validationResult = $this->revisionValidationService->validate($revision);
            if (! $validationResult->isValid()) {
                throw new RevisionApprovalValidationException($validationResult);
            }

            $this->revisionApplyService->apply($revision);

            DB::transaction(function () use ($revision, $reviewer): void {
                $revision->update(['status' => RevisionStatus::Approved]);

                RevisionReview::query()->create([
                    'revision_id' => $revision->id,
                    'actor_user_id' => $reviewer->id,
                    'action' => RevisionReviewAction::Approved,
                    'comment' => null,
                    'actions_snapshot' => null,
                ]);
            });
        } finally {
            $lock->release();
        }
    }

    public function reject(Revision $revision, User $reviewer, string $comment): void
    {
        abort_unless($revision->isPendingReview(), 422, '只有待審核的修訂可以退回');

        $revision->load([
            'actions' => fn ($query) => $query->orderBy('order'),
        ]);

        DB::transaction(function () use ($revision, $reviewer, $comment): void {
            $revision->update(['status' => RevisionStatus::Rejected]);

            RevisionReview::query()->create([
                'revision_id' => $revision->id,
                'actor_user_id' => $reviewer->id,
                'action' => RevisionReviewAction::Rejected,
                'comment' => $comment,
                'actions_snapshot' => $this->buildActionsSnapshot($revision),
            ]);
        });
    }

    private function acquireApplyLock(): Lock
    {
        $graphName = (string) config('cohistograph.app.graph.name');
        $lock = Cache::lock("cohistograph:revision-apply:{$graphName}", 30);

        if (! $lock->get()) {
            throw new CouldNotAcquireRevisionApplyLockException;
        }

        return $lock;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildActionsSnapshot(Revision $revision): array
    {
        return $revision->actions
            ->map(fn ($action): array => [
                'order' => $action->order,
                'action' => $action->action->value,
                'target_age_id' => $action->target_age_id,
                'target_ref_order' => $action->target_ref_order,
                'vertex_type_label' => $action->vertex_type_label,
                'edge_type_label' => $action->edge_type_label,
                'start_vertex_age_id' => $action->start_vertex_age_id,
                'start_vertex_ref_order' => $action->start_vertex_ref_order,
                'end_vertex_age_id' => $action->end_vertex_age_id,
                'end_vertex_ref_order' => $action->end_vertex_ref_order,
                'age_property_name' => $action->age_property_name,
                'value' => $action->value,
            ])
            ->values()
            ->all();
    }
}
