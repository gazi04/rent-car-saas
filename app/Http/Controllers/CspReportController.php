<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives browser Content-Security-Policy violation reports.
 *
 * Exists so a `script-src` regression is visible rather than silent — the CSP is
 * what keeps the parent-scoped session cookie survivable against storefront
 * injection, and the existing policy test only catches a regression somebody
 * commits, not a third-party script a real browser blocks in the field.
 *
 * Treated as hostile input throughout: it is an unauthenticated POST that anyone
 * can send anything to, and on a real site a fair amount of what arrives is
 * browser extensions rather than the app. Hence the size cap, the fixed field
 * allow-list (no dumping of the whole body into the log), the truncation, and
 * the route-level throttle.
 */
class CspReportController extends Controller
{
    /** Reports larger than this are dropped unread — a real report is well under 1 KB. */
    private const int MAX_BODY_BYTES = 8192;

    /** Per-field cap, so a crafted report cannot bloat the log line. */
    private const int MAX_FIELD_LENGTH = 300;

    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();

        // 204 regardless of what we do with it: a browser has nothing useful to
        // do with an error here, and an error page would just be noise.
        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        // Content-Type is application/csp-report, which Laravel's json() does not
        // treat as JSON, so decode the raw body.
        $decoded = json_decode($body, true);

        $report = is_array($decoded) ? ($decoded['csp-report'] ?? null) : null;

        if (! is_array($report)) {
            return response()->noContent();
        }

        Log::warning('CSP violation reported', [
            'tenant_id' => tenant('id'),
            'host' => $request->getHost(),
            'blocked_uri' => $this->field($report, 'blocked-uri'),
            'violated_directive' => $this->field($report, 'violated-directive'),
            'effective_directive' => $this->field($report, 'effective-directive'),
            'document_uri' => $this->field($report, 'document-uri'),
            'script_sample' => $this->field($report, 'script-sample'),
        ]);

        return response()->noContent();
    }

    /**
     * @param  array<mixed, mixed>  $report  decoded from an unauthenticated body, so the shape is not guaranteed
     */
    private function field(array $report, string $key): ?string
    {
        $value = $report[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, self::MAX_FIELD_LENGTH);
    }
}
