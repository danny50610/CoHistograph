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
     * 審核歷程（時間倒序）。
     *
     * 以 revision_reviews 為主（退回含理由／快照；通過僅審核者／時間）。
     * 若 status=approved 但尚無 action=approved 列（歷史缺資料），由 updated_at 合成一筆通過紀錄。
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

        $hasStoredApproval = $reviews->contains(
            fn (RevisionReview $review): bool => $review->action === RevisionReviewAction::Approved,
        );

        if ($this->isApproved() && ! $hasStoredApproval && $this->updated_at !== null) {
            array_unshift($entries, [
                'actor_name' => null,
                'action' => RevisionReviewAction::Approved,
                'comment' => null,
                'occurred_at' => $this->updated_at,
            ]);
        }

        usort(
            $entries,
            fn (array $left, array $right): int => $right['occurred_at'] <=> $left['occurred_at'],
        );

        return $entries;
    }

    /**
     * 列表「最近一次審核」時間：有 revision_reviews 用其最新；
     * 已通過且無審核列時，改以 updated_at（通過當下）表示。
     */
    public function latestReviewAt(): ?Carbon
    {
        /** @var Collection<int, RevisionReview> $reviews */
        $reviews = $this->relationLoaded('reviews')
            ? $this->reviews
            : $this->reviews()->get();

        /** @var RevisionReview|null $latestStored */
        $latestStored = $reviews->sortByDesc('created_at')->first();

        if ($latestStored?->created_at !== null) {
            return $latestStored->created_at;
        }

        if ($this->isApproved()) {
            return $this->updated_at;
        }

        return null;
    }
}
