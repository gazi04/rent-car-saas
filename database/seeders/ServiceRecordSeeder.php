<?php

namespace Database\Seeders;

use App\Models\ServiceRecord;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class ServiceRecordSeeder extends Seeder
{
    /**
     * Seed demo service history for the demo tenant's vehicles (ardi.localhost).
     *
     * Created inside the tenant context so BelongsToTenant auto-fills tenant_id.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->where('email', 'ardi@example.com')->first();

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        $vehicles = Vehicle::all();

        if ($vehicles->isEmpty()) {
            tenancy()->end();

            return;
        }

        ServiceRecord::factory()->forVehicle($vehicles->first())->create();
        ServiceRecord::factory()->forVehicle($vehicles->get(1))->due()->create();
        ServiceRecord::factory()->forVehicle($vehicles->get(2))->overdue()->create();

        tenancy()->end();
    }
}
