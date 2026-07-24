<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

/**
 * Records one EmailLog row per outbound email. Listens on MessageSent, which
 * Laravel dispatches after every mail is handed to the transport — so all
 * mailables in app/Mail are captured without touching them.
 *
 * The row starts as Sent; the Resend webhook later advances its status. When
 * the real Resend transport is in use it stamps an X-Resend-Email-ID header on
 * the message, captured here as message_id so the webhook can match delivery
 * events back to this row (null under the log/array transports in dev/test).
 *
 * tenant_id comes from tenant()?->id: booking mail is queued inside a tenant
 * context (QueueTenancyBootstrapper re-initializes tenancy in the mail job), so
 * it attributes correctly; central mail (e.g. subscription reminders) has no
 * tenant and is logged as a Platform row. Best-effort — a logging failure must
 * never break a live mail send, so the body swallows failures via report().
 *
 * Registered automatically via Laravel's listener discovery (the handle()
 * type-hint) — do NOT also Event::listen() it, or every email is logged twice.
 */
class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;

            $to = $message->getTo();
            if ($to === []) {
                return;
            }

            $header = $message->getHeaders()->get('X-Resend-Email-ID');

            EmailLog::query()->create([
                'tenant_id' => tenant()?->id,
                'message_id' => $header?->getBodyAsString(),
                'to_email' => $to[0]->getAddress(),
                'subject' => $message->getSubject(),
                'mailable' => $event->data['__laravel_mailable'] ?? null,
                'status' => EmailStatus::Sent,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
