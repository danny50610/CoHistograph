<?php

namespace App\Policies;

use App\Models\Revision;
use App\Models\User;

class RevisionPolicy
{
    /**
     * 本人或擁有 revision.review 權限的管理員可以查看
     */
    public function view(User $user, Revision $revision): bool
    {
        return $user->id === $revision->user_id
            || $user->hasPermission('revision.review');
    }

    /**
     * 本人才能更新（狀態合法性由 Service 負責）
     */
    public function update(User $user, Revision $revision): bool
    {
        return $user->id === $revision->user_id;
    }

    /**
     * 本人才能刪除（狀態合法性由 Service 負責）
     */
    public function delete(User $user, Revision $revision): bool
    {
        return $user->id === $revision->user_id;
    }
}
