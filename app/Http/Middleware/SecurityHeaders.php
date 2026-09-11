<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for tenant-facing responses (public storefront +
 * operator panel).
 *
 * The Content-Security-Policy is load-bearing for tenant isolation, not just
 * defence in depth. The session cookie is deliberately scoped to the parent
 * domain (SESSION_DOMAIN=.<domain>) so operator impersonation survives the
 * redirect from the admin host to a tenant subdomain — which means a Super
 * Admin's cookie is sent to every operator-controlled subdomain. Script
 * execution on any storefront would therefore be an admin-takeover path, so
 * blocking injected inline script is what keeps that trade-off safe.
 *
 * 'unsafe-eval' is required: Alpine evaluates its x-* expressions with
 * `new Function`, and Livewire is not running in CSP-safe mode
 * (config/livewire.php `csp_safe` => false). It permits eval from
 * already-trusted scripts; it does NOT permit an injected <script> tag or an
 * inline handler, which is the vector that matters here.
 *
 * 'unsafe-inline' on style-src is required by the branding <style> block in
 * layouts/public.blade.php, which injects the tenant's colour variables.
 *
 * # Why the admin panel is registered as ':without-csp'
 *
 * The storefront emits ZERO inline <script> blocks, which is what makes the
 * strict script-src safe there — and the storefront is the surface that
 * actually renders attacker-reachable content, so that is where the policy
 * earns its keep.
 *
 * Filament panels are different: they emit inline scripts of their own
 * (dark-mode bootstrap, sidebar collapse state, window.filamentData) and
 * Filament ships no CSP nonce support, so a script-src without 'unsafe-inline'
 * silently blocks them. The operator panel has been doing exactly that since
 * the CSP was introduced — see docs/redteam-app-security-2026-09-11.md,
 * Finding 6. Rather than copy a known-broken policy onto the admin panel for
 * the sake of symmetry, the admin panel takes the headers that carry no such
 * cost. The clickjacking protection (X-Frame-Options) was the exploitable gap
 * there; the CSP was defence in depth on an authenticated, Filament-authored
 * surface.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  'with-csp'|'without-csp'|string  $csp  see the class docblock for why
     *                                                the admin panel opts out
     */
    public function handle(Request $request, Closure $next, string $csp = 'with-csp'): Response
    {
        $response = $next($request);

        // Only decorate real HTML pages: streamed file downloads (rental
        // agreements) and JSON endpoints have no use for these.
        if (! $this->isHtml($response)) {
            return $response;
        }

        if ($csp !== 'without-csp') {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function policy(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            'img-src '.implode(' ', $this->imageSources()),
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ];

        // report-uri, not the newer report-to: it is the directive with universal
        // support today, and this is telemetry for a policy whose failure mode is
        // admin takeover — breadth beats elegance. Configurable so it can be
        // repointed at an external collector (Sentry's security endpoint, say)
        // with no code change; null sends no reporting directive at all.
        $reportUri = config('security.csp_report_uri');

        if (is_string($reportUri) && $reportUri !== '') {
            $directives[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $directives);
    }

    /**
     * Vehicle photos and operator logos are served from whichever disk
     * MEDIA_DISK names — the local public disk by default, an S3 bucket on
     * Laravel Cloud. A CSP that assumed 'self' would blank every photo there,
     * so the configured disk's public URL is added when it is off-origin.
     *
     * @return list<string>
     */
    private function imageSources(): array
    {
        $sources = ["'self'", 'data:', 'blob:'];

        $mediaDisk = config()->string('media-library.disk_name');
        $url = config()->string("filesystems.disks.{$mediaDisk}.url", '');

        $host = $url === '' ? null : parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $sources[] = (is_string($scheme) && $scheme !== '' ? $scheme : 'https').'://'.$host;
        }

        return $sources;
    }
}
