<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminds an operator their manually billed period is about to end (sent by the
 * daily subscription sweep at each configured reminder threshold). A tenant still
 * on the trial plan gets trial-specific copy; everyone else gets renewal copy.
 */
class SubscriptionRenewalReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, ThrottlesMailQueue;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __("emails.{$this->langKey()}.subject", ['days' => $this->daysLeft]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-renewal-reminder',
            with: [
                'tenant' => $this->tenant,
                'daysLeft' => $this->daysLeft,
                'langKey' => $this->langKey(),
            ],
        );
    }

    public function langKey(): string
    {
        return $this->tenant->plan === 'trial'
            ? 'trial_expiring_reminder'
            : 'subscription_renewal_reminder';
    }
}
