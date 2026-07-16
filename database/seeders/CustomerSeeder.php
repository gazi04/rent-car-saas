<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Seed demo customers for the demo tenant (ardi.localhost).
     *
     * Created inside the tenant context so BelongsToTenant auto-fills tenant_id.
     */
    public function run(): void
    {
        $tenant = Tenant::where('email', 'ardi@example.com')->first();

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        Customer::factory()->count(4)->create();
        Customer::factory()->blacklisted()->create();

        tenancy()->end();
    }
}
