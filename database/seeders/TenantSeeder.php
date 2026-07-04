<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Seed a demo tenant, its domain, and its operator login for local development.
     *
     * Mirrors what the Filament admin panel will later do when an operator
     * registration is approved. Visit http://ardi.localhost:8000 after running,
     * and log into the operator dashboard at http://ardi.localhost:8000/dashboard
     * with the credentials below.
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['email' => 'ardi@example.com'],
            [
                'name' => 'Ardi Rent A Car',
                'phone' => '+38344123456',
                'status' => 'active',
                'plan' => 'trial',
                'trial_ends_at' => now()->addDays(30),
            ],
        );

        $base = config('tenancy.tenant_base_domain', 'localhost');
        $tenant->domains()->firstOrCreate(['domain' => 'ardi.'.$base]);

        // `role` and `tenant_id` are not mass-assignable, so set them via forceFill —
        // same pattern as AdminUserSeeder and the operator self-registration flow.
        $operator = User::firstOrNew(['email' => 'ardi@example.com']);

        $operator->forceFill([
            'name' => 'Ardi Operator',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => 'operator',
            'email_verified_at' => now(),
        ])->save();
    }
}
