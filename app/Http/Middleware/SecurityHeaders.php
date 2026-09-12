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
 * # Why the Filament panels keep this policy, and what it costs
 *
 * Filament emits raw inline <script> blocks and ships NO CSP nonce support
 * (there is nothing matching `nonce` anywhere in vendor/filament), so a
 * script-src without 'unsafe-inline' refuses them. On the operator panel that is
 * three blocks: the dark-mode bootstrap, its invocation, and
 * `window.filamentData`. It has been true since this CSP was introduced.
 *
 * That is accepted deliberately, because all three are PRE-PAINT guards rather
 * than functionality. filament/filament/dist/index.js — external, so it loads
 * fine — re-reads localStorage.theme at alpine:init and applies the `dark` class
 * through an Alpine.effect, and the sidebar block seeds a collapsed-group list
 * that is empty in this app (neither panel declares navigation groups). The cost
 * of refusing them was a flash of the light theme on every page load, not a
 * broken panel. That flash is now gone too: resources/js/filament-theme-bootstrap.js
 * does the pre-paint pass from 'self', emitted at PanelsRenderHook::HEAD_END by
 * both panel providers.
 *
 * Relaxing script-src for the panels instead would have been a bad trade. The
 * operator panel is served from operatorname.<domain>/dashboard — the SAME ORIGIN
 * as that tenant's public storefront. 'unsafe-inline' there is 'unsafe-inline' on
 * the origin whose XSS is admin-session theft, bought to remove a flash.
 *
 * Six further Filament script blocks look inline but are not: Livewire's @script
 * ships them as an `effects.scripts` JSON payload and runs them through Alpine's
 * `new Function` evaluator, which is 'unsafe-eval', not 'unsafe-inline'. The same
 * goes for x-data/x-load (the fullcalendar widget). None of those are affected.
 *
 * The ':without-csp' parameter is retained but unused by app code: the admin
 * panel took the full policy on 2026-09-12, once the above disproved the premise
 * it had been exempted on. It stays as the lever for a future surface that
 * genuinely cannot take a CSP.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  'with-csp'|'without-csp'|string  $csp  the opt-out lever; no app
     *                                                surface uses it today, see
     *                                                the class docblock
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
