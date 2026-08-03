<?php

namespace App\Models;

use App\Enums\RevisionReviewAction;
use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property \App\Enums\RevisionStatus $status
 * @property bool|null $last_validation_is_valid
 * @property string|null $last_validation_summary
 * @property array<int, string>|null $last_validation_general_errors
 * @property array<int, list<string>>|null $last_validation_action_errors
 * @property \Illuminate\Support\Carbon|null $last_validated_at
 * @property int $user_id
 * @property bool $is_ai_assisted
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Revision extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'title',
        'description',
        'status',
        'last_validation_is_valid',
        'last_validation_summary',
        'last_validation_general_errors',
        'last_validation_action_errors',
        'last_validated_at',
        'user_id',
        'is_ai_assisted',
    ];

    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'last_validation_is_valid' => 'boolean',
            'last_validation_general_errors' => 'array',
            'last_validation_action_errors' => 'array',
            'last_validated_at' => 'datetime',
            'is_ai_assisted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RevisionAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(RevisionAction::class);
    }

    /** @return HasMany<RevisionReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(RevisionReview::class);
    }

    public function isDraft(): bool
    {
        return $this->status === RevisionStatus::Draft;
    }

    public function isPendingReview(): bool
    {
        return $this->status === RevisionStatus::PendingReview;
    }

    public function isRejected(): bool
    {
        return $this->status === RevisionStatus::Rejected;
    }

    public function isApproved(): bool
    {
        return $this->status === RevisionStatus::Approved;
    }

    public function latestReview(): ?RevisionReview
    {
        /** @var RevisionReview|null */
        return $this->reviews()->latest()->first();
    }

    /**
     * 審核歷程（時間倒序），來自 revision_reviews。
     *
     * @return list<array{
     *     actor_name: string|null,
     *     action: RevisionReviewAction,
     *     comment: string|null,
     *     occurred_at: Carbon
     * }>
     */
    public function reviewHistoryEntries(): array
    {
        /** @var Collection<int, RevisionReview> $reviews */
        $reviews = $this->relationLoaded('reviews')
            ? $this->reviews
            : $this->reviews()->with('actorUser')->get();

        /** @var list<array{actor_name: string|null, action: RevisionReviewAction, comment: string|null, occurred_at: Carbon}> $entries */
        $entries = [];

        foreach ($reviews->sortByDesc('created_at') as $review) {
            $occurredAt = $review->created_at;
            if ($occurredAt === null) {
                continue;
            }

            $entries[] = [
                'actor_name' => $review->actorUser?->name,
                'action' => $review->action,
                'comment' => $review->comment,
                'occurred_at' => $occurredAt,
            ];
        }

        return $entries;
    }
}
