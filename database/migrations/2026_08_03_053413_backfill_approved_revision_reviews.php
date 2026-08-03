<?php

use App\Enums\RevisionReviewAction;
use App\Enums\RevisionStatus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 為 status=approved 但缺少 action=approved 審核列的修訂補上紀錄。
     *
     * 歷史資料無法還原真實審核者：優先使用具 revision.review 權限的使用者；
     * 若系統尚無審核者，則暫用該修訂的擁有者以满足 FK。
     * created_at / updated_at 以 revision.updated_at 近似通過時間。
     *
     * 此 migration 不可逆（無法區分回填列與正常 approve() 寫入的列）。
     */
    public function up(): void
    {
        $defaultActorUserId = $this->resolveDefaultActorUserId();

        $approvedRevisionIdsMissingReview = DB::table('revisions')
            ->where('status', RevisionStatus::Approved->value)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('revision_reviews')
                    ->whereColumn('revision_reviews.revision_id', 'revisions.id')
                    ->where('revision_reviews.action', RevisionReviewAction::Approved->value);
            })
            ->get(['id', 'user_id', 'updated_at']);

        $now = now();

        foreach ($approvedRevisionIdsMissingReview as $revision) {
            $occurredAt = $revision->updated_at ?? $now;

            DB::table('revision_reviews')->insert([
                'revision_id' => $revision->id,
                'actor_user_id' => $defaultActorUserId ?? $revision->user_id,
                'action' => RevisionReviewAction::Approved->value,
                'comment' => null,
                'actions_snapshot' => null,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible: cannot distinguish backfilled rows from normal approve() rows.
    }

    private function resolveDefaultActorUserId(): ?int
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'revision.review')
            ->value('id');

        if ($permissionId === null) {
            return null;
        }

        $directUserId = DB::table('permission_user')
            ->where('permission_id', $permissionId)
            ->where('user_type', User::class)
            ->orderBy('user_id')
            ->value('user_id');

        if ($directUserId !== null) {
            return (int) $directUserId;
        }

        $roleUserId = DB::table('permission_role')
            ->where('permission_role.permission_id', $permissionId)
            ->join('role_user', 'role_user.role_id', '=', 'permission_role.role_id')
            ->where('role_user.user_type', User::class)
            ->orderBy('role_user.user_id')
            ->value('role_user.user_id');

        return $roleUserId !== null ? (int) $roleUserId : null;
    }
};
