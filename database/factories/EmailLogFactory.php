<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\EmailLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'message_id' => fake()->uuid(),
            'to_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'mailable' => BookingConfirmedMail::class,
            'status' => EmailStatus::Sent,
            'error' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => ['status' => EmailStatus::Delivered]);
    }

    public function bounced(): static
    {
        return $this->state(fn (): array => [
            'status' => EmailStatus::Bounced,
            'error' => 'The recipient mailbox does not exist.',
        ]);
    }

    public function complained(): static
    {
        return $this->state(fn (): array => ['status' => EmailStatus::Complained]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->id]);
    }
}
