<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the central Super Admin who manages operators in the Filament admin panel.
     *
     * `role` and `tenant_id` are not mass-assignable, so set them via forceFill.
     * Log in at http://admin.localhost:8000 with these credentials locally.
     */
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'admin@yourdomain.com']);

        $user->forceFill([
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => null,
            'email_verified_at' => now(),
        ])->save();
    }
}
