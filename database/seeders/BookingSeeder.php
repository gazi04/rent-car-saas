<?php

namespace Database\Seeders;

use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Seed demo bookings and a manual block for the demo tenant (ardi.localhost).
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

        $vehicles = Vehicle::all();

        if ($vehicles->isEmpty()) {
            tenancy()->end();

            return;
        }

        // A mix of statuses across vehicles.
        Booking::factory()->forVehicle($vehicles->first())->confirmed()->create();
        Booking::factory()->forVehicle($vehicles->first())->active()->create();
        Booking::factory()->forVehicle($vehicles->get(1))->create();
        Booking::factory()->forVehicle($vehicles->get(1))->completed()->create();
        Booking::factory()->forVehicle($vehicles->get(2))->cancelled()->create();

        // One manual maintenance block.
        BlockedDate::factory()->forVehicle($vehicles->first())->create([
            'reason' => 'maintenance',
        ]);

        tenancy()->end();
    }
}
