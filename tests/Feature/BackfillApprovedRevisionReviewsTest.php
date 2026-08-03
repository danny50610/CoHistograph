<?php

namespace Tests\Feature;

use App\Enums\RevisionReviewAction;
use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\RevisionReview;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BackfillApprovedRevisionReviewsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_backfills_missing_approved_review_rows(): void
    {
        $reviewer = User::factory()->createOne();
        $reviewer->givePermission('revision.review');

        $owner = User::factory()->createOne();
        $revision = Revision::query()->create([
            'title' => 'Approved Missing Review',
            'description' => 'needs backfill',
            'status' => RevisionStatus::Approved,
            'user_id' => $owner->id,
        ]);

        $this->assertDatabaseMissing('revision_reviews', [
            'revision_id' => $revision->id,
            'action' => RevisionReviewAction::Approved->value,
        ]);

        $this->runBackfillMigration();

        $review = RevisionReview::query()
            ->where('revision_id', $revision->id)
            ->where('action', RevisionReviewAction::Approved)
            ->firstOrFail();

        $this->assertNull($review->comment);
        $this->assertNull($review->actions_snapshot);
        $this->assertTrue($review->created_at?->equalTo($revision->updated_at));
        $this->assertTrue(
            User::query()->findOrFail($review->actor_user_id)->hasPermission('revision.review'),
        );
    }

    public function test_migration_does_not_duplicate_existing_approved_review(): void
    {
        $reviewer = User::factory()->createOne();
        $reviewer->givePermission('revision.review');

        $owner = User::factory()->createOne();
        $revision = Revision::query()->create([
            'title' => 'Already Has Approved Review',
            'description' => null,
            'status' => RevisionStatus::Approved,
            'user_id' => $owner->id,
        ]);

        RevisionReview::query()->create([
            'revision_id' => $revision->id,
            'actor_user_id' => $reviewer->id,
            'action' => RevisionReviewAction::Approved,
            'comment' => null,
        ]);

        $this->runBackfillMigration();

        $this->assertSame(
            1,
            RevisionReview::query()
                ->where('revision_id', $revision->id)
                ->where('action', RevisionReviewAction::Approved)
                ->count(),
        );
    }

    private function runBackfillMigration(): void
    {
        $migration = require database_path('migrations/2026_08_03_053413_backfill_approved_revision_reviews.php');

        $this->assertIsObject($migration);
        $this->assertTrue(method_exists($migration, 'up'));

        $migration->up();
    }
}
