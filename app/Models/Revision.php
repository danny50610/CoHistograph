<?php

namespace App\Models;

use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Revision extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RevisionAction::class);
    }

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
        return $this->reviews()->latest()->first();
    }
}
