<?php

namespace App\Models;

use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'last_validation_is_valid' => 'boolean',
            'last_validation_general_errors' => 'array',
            'last_validation_action_errors' => 'array',
            'last_validated_at' => 'datetime',
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
}
