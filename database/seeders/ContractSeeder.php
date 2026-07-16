<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * Seed rental-agreement contract rows for the demo tenant's confirmed and
     * completed bookings (ardi.localhost). One contract per booking (1:1).
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

        Booking::whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed])
            ->get()
            ->each(fn (Booking $booking) => Contract::factory()->forBooking($booking)->create());

        tenancy()->end();
    }
}
