<?php

namespace App\Models;

use App\Enums\RevisionReviewAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $revision_id
 * @property int $actor_user_id
 * @property RevisionReviewAction $action
 * @property string|null $comment
 * @property list<array<string, mixed>>|null $actions_snapshot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $actorUser
 */
class RevisionReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'revision_id',
        'actor_user_id',
        'action',
        'comment',
        'actions_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'action' => RevisionReviewAction::class,
            'actions_snapshot' => 'array',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
