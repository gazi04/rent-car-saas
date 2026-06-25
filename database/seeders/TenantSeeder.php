<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Seed a demo tenant for local development.
     *
     * Mirrors what the Filament admin panel will later do when an operator
     * registration is approved. Visit http://ardi.localhost:8000 after running.
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

        $tenant->domains()->firstOrCreate(['domain' => 'ardi.localhost']);
    }
}
