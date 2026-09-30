<?php

namespace App\Policies;

use App\Models\Usaha;
use App\Models\User;

class UsahaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Usaha $record): bool
    {
        $usaha = $record;

        return $usaha !== null && ! $usaha->trashed() && (int) $usaha->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Usaha $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Usaha $record): bool
    {
        return false;
    }
}
