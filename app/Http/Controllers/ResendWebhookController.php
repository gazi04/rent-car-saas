<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Resend delivery webhooks (Svix-signed) and advances the matching
 * EmailLog row's status. Public + unauthenticated + CSRF-exempt — the Svix
 * signature is the only trust boundary, so it is verified before anything else.
 *
 * Always returns 200 for a validly-signed request, even when no row matches
 * (e.g. an email sent before this feature shipped), so Resend stops retrying.
 * An invalid or missing signature returns 400 and touches nothing.
 */
class ResendWebhookController extends Controller
{
    /**
     * Resend event type => the status it moves a row to.
     */
    private const array STATUS_MAP = [
        'email.delivered' => EmailStatus::Delivered,
        'email.bounced' => EmailStatus::Bounced,
        'email.complained' => EmailStatus::Complained,
        'email.delivery_delayed' => EmailStatus::DelayedDelivery,
    ];

    public function __invoke(Request $request): Response
    {
        $secret = config('services.resend.webhook_secret');

        if (! is_string($secret) || $secret === '' || ! $this->signatureIsValid($request, $secret)) {
            return response('Invalid signature.', 400);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $status = self::STATUS_MAP[$type] ?? null;

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $emailId = is_string($data['email_id'] ?? null) ? $data['email_id'] : null;

        if ($status === null || $emailId === null) {
            // A signed event we don't track (or missing id) — acknowledge, no-op.
            return response('OK', 200);
        }

        $log = EmailLog::query()->where('message_id', $emailId)->first();

        if ($log !== null) {
            $log->status = $status;

            if ($status === EmailStatus::Bounced || $status === EmailStatus::Complained) {
                $log->error = $this->extractReason($data);
            }

            $log->save();
        }

        return response('OK', 200);
    }

    /**
     * Verify the Svix signature (the scheme Resend uses). The secret is
     * base64-encoded after a `whsec_` prefix; the signed content is
     * `{id}.{timestamp}.{rawBody}`; the header carries one or more
     * space-separated `v1,<base64sig>` entries — any match passes.
     */
    private function signatureIsValid(Request $request, string $secret): bool
    {
        $id = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');
        $signatureHeader = $request->header('svix-signature');

        if (! is_string($id) || ! is_string($timestamp) || ! is_string($signatureHeader)) {
            return false;
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false) {
            return false;
        }

        $signedContent = $id.'.'.$timestamp.'.'.$request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $key, true));

        foreach (explode(' ', $signatureHeader) as $entry) {
            $parts = explode(',', $entry, 2);
            if (count($parts) === 2 && hash_equals($expected, $parts[1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull a human-readable bounce/complaint reason out of the event data.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractReason(array $data): ?string
    {
        foreach (['reason', 'message', 'description'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        $bounce = $data['bounce'] ?? null;
        if (is_array($bounce) && is_string($bounce['message'] ?? null)) {
            return $bounce['message'];
        }

        return null;
    }
}
