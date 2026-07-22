<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Seed demo customer reviews for the demo tenant (ardi.localhost): one
     * approved review on the completed booking, one still pending moderation
     * on the confirmed booking.
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

        $completed = Booking::query()->where('status', BookingStatus::Completed)->first();
        $confirmed = Booking::query()->where('status', BookingStatus::Confirmed)->first();

        if ($completed !== null) {
            Review::factory()->approved()->create([
                'booking_id' => $completed->id,
                'vehicle_id' => $completed->vehicle_id,
                'customer_id' => $completed->customer_id,
            ]);
        }

        if ($confirmed !== null) {
            Review::factory()->create([
                'booking_id' => $confirmed->id,
                'vehicle_id' => $confirmed->vehicle_id,
                'customer_id' => $confirmed->customer_id,
            ]);
        }

        tenancy()->end();
    }
}
