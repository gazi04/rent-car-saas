<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * `tenant_id` is intentionally omitted — BelongsToTenant fills it from context.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'path' => 'tenants/1/contracts/'.$this->faker->bothify('BK-????##').'.pdf',
            'generated_at' => now(),
        ];
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes): array => [
            'booking_id' => $booking->id,
            'path' => "tenants/{$booking->tenant_id}/contracts/{$booking->reference}.pdf",
        ]);
    }
}
