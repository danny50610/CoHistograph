<?php

namespace App\Services;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\RevisionAction;
use App\Models\User;
use App\Services\Revision\RevisionValidationResult;
use App\Services\Revision\RevisionValidationService;
use App\Support\RevisionActionRefOrderRemapper;
use App\Support\RevisionActionValueNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevisionService
{
    public function __construct(
        private RevisionValidationService $revisionValidationService,
        private RevisionActionValueNormalizer $actionValueNormalizer,
        private RevisionActionRefOrderRemapper $refOrderRemapper,
    ) {}

    public function create(User $user, array $data, bool $aiAssisted = false): Revision
    {
        return Revision::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
            'is_ai_assisted' => $aiAssisted,
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
                $revision->actions()->create($this->actionAttributes($actionData, $index));
            }
        });

        $this->refreshValidation($revision);
    }

    /**
     * @param  array{title: string, description?: string|null}  $data
     */
    public function updateMetadata(Revision $revision, array $data): void
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以更新');

        $this->markAiAssisted($revision);

        $revision->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        $this->refreshValidation($revision);
    }

    /**
     * @param  array<string, mixed>  $actionData
     */
    public function addAction(Revision $revision, int $order, array $actionData): RevisionAction
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以更新');

        $this->markAiAssisted($revision);

        $action = DB::transaction(function () use ($revision, $order, $actionData) {
            $actions = $revision->actions()->orderBy('order')->get();
            $insertAt = max(0, min($order, $actions->count()));
            $mapping = $this->refOrderRemapper->mappingForInsert($actions->count(), $insertAt);

            foreach ($actions as $existing) {
                $this->applyOrderAndRefMapping($existing, $mapping);
            }

            return $revision->actions()->create(
                $this->actionAttributes($this->refOrderRemapper->remapAction($actionData, $mapping), $insertAt),
            );
        });

        $this->refreshValidation($revision);

        return $action->fresh();
    }

    /**
     * @param  array<string, mixed>  $actionData
     */
    public function updateAction(Revision $revision, RevisionAction $action, array $actionData): RevisionAction
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以更新');
        abort_unless($action->revision_id === $revision->id, 404);

        $this->markAiAssisted($revision);

        $action->update($this->actionAttributes($actionData, $action->order));

        $this->refreshValidation($revision);

        return $action->fresh();
    }

    public function deleteAction(Revision $revision, RevisionAction $action): void
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以更新');
        abort_unless($action->revision_id === $revision->id, 404);

        $this->markAiAssisted($revision);

        DB::transaction(function () use ($revision, $action) {
            $actions = $revision->actions()->orderBy('order')->get();
            $mapping = $this->refOrderRemapper->mappingForDelete($actions->count(), (int) $action->order);

            $action->delete();

            foreach ($actions as $existing) {
                if ($existing->id === $action->id) {
                    continue;
                }

                $this->applyOrderAndRefMapping($existing, $mapping);
            }
        });

        $this->refreshValidation($revision);
    }

    public function moveAction(Revision $revision, RevisionAction $action, ?int $toOrder = null, ?string $direction = null): void
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以更新');
        abort_unless($action->revision_id === $revision->id, 404);

        if (($toOrder === null && $direction === null) || ($toOrder !== null && $direction !== null)) {
            throw new InvalidArgumentException('Provide exactly one of to_order or direction');
        }

        $this->markAiAssisted($revision);

        DB::transaction(function () use ($revision, $action, $toOrder, $direction) {
            $actions = $revision->actions()->orderBy('order')->get()->values();
            $fromIndex = $actions->search(fn (RevisionAction $item) => $item->id === $action->id);

            if ($fromIndex === false) {
                abort(404);
            }

            if ($direction !== null) {
                if (! in_array($direction, ['up', 'down'], true)) {
                    throw new InvalidArgumentException('direction must be up or down');
                }

                $targetIndex = $direction === 'up' ? $fromIndex - 1 : $fromIndex + 1;
                if ($targetIndex < 0 || $targetIndex >= $actions->count()) {
                    throw new InvalidArgumentException($direction === 'up' ? 'Already the first action' : 'Already the last action');
                }
            } else {
                $targetIndex = max(0, min((int) $toOrder, $actions->count() - 1));
            }

            if ($targetIndex === $fromIndex) {
                return;
            }

            $mapping = $this->refOrderRemapper->mappingForMove($actions->count(), $fromIndex, $targetIndex);

            foreach ($actions as $existing) {
                $this->applyOrderAndRefMapping($existing, $mapping);
            }
        });

        $this->refreshValidation($revision);
    }

    public function validateAndCache(Revision $revision): RevisionValidationResult
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以重新驗證並寫入快取');

        return $this->refreshValidation($revision);
    }

    public function submit(Revision $revision): RevisionValidationResult
    {
        abort_unless($revision->isDraft(), 422, '只有草稿狀態的修訂可以提交審核');

        $revision->load('actions');
        $validationResult = $this->revisionValidationService->validate($revision);

        $revision->update([
            'last_validation_is_valid' => $validationResult->isValid(),
            'last_validation_summary' => $validationResult->checkSummary(),
            'last_validation_general_errors' => $validationResult->generalErrors(),
            'last_validation_action_errors' => $validationResult->actionMessages(),
            'last_validation_action_warnings' => $validationResult->actionWarnings(),
            'last_validated_at' => now(),
            ...($validationResult->isValid()
                ? ['status' => RevisionStatus::PendingReview]
                : []),
        ]);

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
                return new RevisionAction($this->actionAttributes($actionData, $index));
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

    /**
     * @return array{
     *     revision: array<string, mixed>,
     *     actions: list<array<string, mixed>>,
     *     validation: array{
     *         is_valid: bool|null,
     *         summary: string|null,
     *         general_errors: array<int, string>,
     *         action_errors: array<int, list<string>>,
     *         action_warnings: array<int, array<int, array{code:string,message:string,meta:array<string,mixed>}>>
     *     }
     * }
     */
    public function toChangeResponse(Revision $revision): array
    {
        $revision->refresh()->load(['actions' => fn ($query) => $query->orderBy('order')]);

        return [
            'revision' => [
                'id' => $revision->id,
                'title' => $revision->title,
                'description' => $revision->description,
                'status' => $revision->status->value,
                'is_ai_assisted' => $revision->is_ai_assisted,
                'last_validated_at' => $revision->last_validated_at?->toIso8601String(),
            ],
            'actions' => $revision->actions
                ->map(fn (RevisionAction $action): array => $this->formatAction($action))
                ->values()
                ->all(),
            'validation' => [
                'is_valid' => $revision->last_validation_is_valid,
                'summary' => $revision->last_validation_summary,
                'general_errors' => $revision->last_validation_general_errors ?? [],
                'action_errors' => $revision->last_validation_action_errors ?? [],
                'action_warnings' => $revision->last_validation_action_warnings ?? [],
            ],
        ];
    }

    public function refreshValidation(Revision $revision): RevisionValidationResult
    {
        $revision->refresh()->load(['actions' => fn ($query) => $query->orderBy('order')]);

        $validationResult = $this->revisionValidationService->validate($revision);

        $revision->update([
            'last_validation_is_valid' => $validationResult->isValid(),
            'last_validation_summary' => $validationResult->checkSummary(),
            'last_validation_general_errors' => $validationResult->generalErrors(),
            'last_validation_action_errors' => $validationResult->actionMessages(),
            'last_validation_action_warnings' => $validationResult->actionWarnings(),
            'last_validated_at' => now(),
        ]);

        return $validationResult;
    }

    /**
     * Sticky flag: once an MCP tool creates or edits a revision, mark it as AI-assisted.
     */
    private function markAiAssisted(Revision $revision): void
    {
        if ($revision->is_ai_assisted) {
            return;
        }

        $revision->forceFill(['is_ai_assisted' => true])->save();
    }

    /**
     * @param  array<int, int|null>  $oldToNew
     */
    private function applyOrderAndRefMapping(RevisionAction $action, array $oldToNew): void
    {
        $newOrder = $oldToNew[(int) $action->order] ?? null;

        if ($newOrder === null) {
            return;
        }

        $action->update([
            'order' => $newOrder,
            ...$this->refOrderRemapper->remapAction([
                'target_ref_order' => $action->target_ref_order,
                'start_vertex_ref_order' => $action->start_vertex_ref_order,
                'end_vertex_ref_order' => $action->end_vertex_ref_order,
            ], $oldToNew),
        ]);
    }

    /**
     * @param  array<string, mixed>  $actionData
     * @return array<string, mixed>
     */
    private function actionAttributes(array $actionData, int $order): array
    {
        return [
            'order' => $order,
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
            'value' => $this->actionValueNormalizer->normalize($actionData),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAction(RevisionAction $action): array
    {
        $payload = [
            'id' => $action->id,
            'order' => $action->order,
            'action' => $action->action->value,
        ];

        foreach ([
            'target_age_id',
            'target_ref_order',
            'vertex_type_label',
            'edge_type_label',
            'start_vertex_age_id',
            'start_vertex_ref_order',
            'end_vertex_age_id',
            'end_vertex_ref_order',
            'age_property_name',
            'value',
        ] as $field) {
            $value = $action->{$field};
            if ($value !== null) {
                if ($field === 'value' && is_string($value)) {
                    $decoded = json_decode($value, true);
                    $payload[$field] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                } else {
                    $payload[$field] = $value;
                }
            }
        }

        return $payload;
    }
}
