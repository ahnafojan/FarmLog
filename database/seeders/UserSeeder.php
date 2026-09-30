<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $password = Str::password(20);

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => $password,
                'is_admin' => false,
            ],
        );

        if ($user->wasRecentlyCreated) {
            $this->command?->info('Email: '.$user->email);
            $this->command?->info('Password: '.$password);
        } else {
            $this->command?->info('User test@example.com sudah tersedia; data akun tidak diubah.');
        }
    }
}
