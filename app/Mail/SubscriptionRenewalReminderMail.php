<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminds an operator their manually billed period is ending or has just lapsed
 * (sent by the daily subscription sweep at each configured reminder threshold).
 *
 * Two axes decide the copy, and neither is the plan tier — trial and paid
 * subscriptions ride the same lifecycle, only the wording differs:
 *  - before/after lapse: a negative $daysLeft threshold means the period already
 *    ended and the tenant is inside the grace window (its last warning before
 *    automatic suspension).
 *  - trial vs paid: Tenant::isOnTrial().
 */
class SubscriptionRenewalReminderMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    /**
     * @param  int  $daysLeft  The reminder threshold from config('billing.reminder_days'):
     *                         positive = days before paid_until, negative = days after it lapsed.
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(sprintf('emails.%s.subject', $this->langKey()), ['days' => $this->displayDays()]),
        );
    }

    /**
     * Note the view variable is `displayDays`, not `daysLeft`: Mailable::buildViewData()
     * merges public properties *after* this array, so a `daysLeft` key here would be
     * silently overwritten by the raw $daysLeft threshold and render "-1 day(s)".
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-renewal-reminder',
            with: [
                'tenant' => $this->tenant,
                'displayDays' => $this->displayDays(),
                'suspendsOn' => $this->suspendsOn()->format('d M Y'),
                'langKey' => $this->langKey(),
            ],
        );
    }

    /** The period already lapsed and the tenant is inside the grace window. */
    public function isGrace(): bool
    {
        return $this->daysLeft < 0;
    }

    public function langKey(): string
    {
        $trial = $this->tenant->isOnTrial();

        return match (true) {
            $this->isGrace() && $trial => 'trial_grace_reminder',
            $this->isGrace() => 'subscription_grace_reminder',
            $trial => 'trial_expiring_reminder',
            default => 'subscription_renewal_reminder',
        };
    }

    /**
     * The number the operator actually cares about: days of grace left before
     * suspension once lapsed, otherwise days until the period ends. Never the
     * raw negative threshold, which would render as "ends in -1 day(s)".
     */
    public function displayDays(): int
    {
        return $this->isGrace()
            ? (int) config('billing.grace_days')
            : $this->daysLeft;
    }

    /** The date after which the daily sweep suspends this tenant. */
    public function suspendsOn(): CarbonInterface
    {
        return $this->tenant->paid_until->addDays((int) config('billing.grace_days'));
    }
}
