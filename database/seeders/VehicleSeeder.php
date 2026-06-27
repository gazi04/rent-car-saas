<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Seed a few vehicles for the demo tenant (ardi.localhost).
     *
     * Vehicles are created inside the tenant context so the BelongsToTenant
     * trait auto-fills tenant_id. Tenancy is reverted afterwards so later
     * seeders run on the central connection.
     */
    public function run(): void
    {
        // Matches the demo tenant created in TenantSeeder.
        $tenant = Tenant::where('email', 'ardi@example.com')->first();

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        Vehicle::factory()->count(6)->create();
        Vehicle::factory()->private()->create();
        Vehicle::factory()->underMaintenance()->create();

        tenancy()->end();
    }
}
