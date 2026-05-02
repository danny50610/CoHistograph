<?php

namespace App\Services;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;

class RevisionService
{
    public function create(User $user, array $data): Revision
    {
        return Revision::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);
    }
}
