<?php

namespace App\Policies;

use App\Models\Kategori;
use App\Models\User;

class KategoriPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Kategori $record): bool
    {
        $usaha = $record->usaha;

        return $usaha !== null && ! $usaha->trashed() && (int) $usaha->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Kategori $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Kategori $record): bool
    {
        return false;
    }
}
