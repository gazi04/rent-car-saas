<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerts platform admins that a tenant was auto-suspended because its manually
 * billed subscription lapsed past the grace period. The Filament bell entry is
 * sent separately by the subscription sweep (Filament's own database format).
 */
class TenantSubscriptionSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Tenant $tenant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tenant suspended — {$this->tenant->name}")
            ->line("The subscription of {$this->tenant->name} lapsed more than 7 days ago and the tenant has been suspended automatically.")
            ->line('Paid until: '.$this->tenant->paid_until?->toFormattedDateString())
            ->line('Record a payment in the admin panel to reactivate them.');
    }
}
