<?php

namespace Database\Seeders;

use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
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

        $customer = Customer::query()->first();
        $promoCode = PromoCode::query()->where('is_active', true)->first();

        // A mix of statuses across vehicles.
        Booking::factory()->forVehicle($vehicles->first())->confirmed()->create([
            'customer_id' => $customer?->id,
        ]);
        Booking::factory()->forVehicle($vehicles->first())->active()->create();
        Booking::factory()->forVehicle($vehicles->get(1))->create([
            'promo_code_id' => $promoCode?->id,
        ]);
        Booking::factory()->forVehicle($vehicles->get(1))->completed()->create([
            'customer_id' => $customer?->id,
        ]);
        Booking::factory()->forVehicle($vehicles->get(2))->cancelled()->create();

        // One manual maintenance block.
        BlockedDate::factory()->forVehicle($vehicles->first())->create([
            'reason' => 'maintenance',
        ]);

        tenancy()->end();
    }
}
