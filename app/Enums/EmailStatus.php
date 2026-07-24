<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Delivery status of a logged outbound email. A row starts as Sent (written by
 * the LogSentEmail listener when Laravel hands the message to Resend) and is
 * later advanced by the Resend webhook (ResendWebhookController) as delivery
 * events arrive. DelayedDelivery is a transient Resend state (a retry is in
 * progress) — it may still resolve to Delivered or Bounced afterwards.
 */
enum EmailStatus: string implements HasLabel
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case DelayedDelivery = 'delayed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::DelayedDelivery => 'Delayed',
        };
    }

    /**
     * Filament badge color for the table/widget.
     */
    public function color(): string
    {
        return match ($this) {
            self::Sent => 'gray',
            self::Delivered => 'success',
            self::Bounced => 'danger',
            self::Complained => 'warning',
            self::DelayedDelivery => 'info',
        };
    }
}
