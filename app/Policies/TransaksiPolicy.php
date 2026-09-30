<?php

namespace App\Policies;

use App\Models\Transaksi;
use App\Models\User;

class TransaksiPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaksi $record): bool
    {
        $usaha = $record->usaha;

        return $usaha !== null && ! $usaha->trashed() && (int) $usaha->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaksi $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Transaksi $record): bool
    {
        return $this->view($user, $record);
    }
}
