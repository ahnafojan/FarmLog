<?php

namespace App\Policies;

use App\Models\SiklusPenanda;
use App\Models\User;

class SiklusPenandaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SiklusPenanda $record): bool
    {
        $usaha = $record->siklus?->usaha;

        return $usaha !== null && ! $usaha->trashed() && (int) $usaha->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SiklusPenanda $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, SiklusPenanda $record): bool
    {
        return false;
    }
}
