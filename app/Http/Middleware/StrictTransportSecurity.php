<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits Strict-Transport-Security, instructing browsers to reach this origin
 * over HTTPS only.
 *
 * # Why this matters more here than in a typical app
 *
 * SESSION_DOMAIN is scoped to the parent domain so operator impersonation
 * survives the redirect from the admin host to a tenant subdomain. One cookie
 * is therefore valid on every operator-controlled subdomain, and the same
 * config governs the long-lived remember-me cookie (CookieServiceProvider
 * seeds the CookieJar defaults from the session config). HSTS removes the
 * plaintext round-trip in which an SSL-stripping proxy could read or overwrite
 * either one.
 *
 * Note what this does NOT fix, so the two controls are not confused: the
 * cookie's own Secure attribute comes from config/session.php. Symfony already
 * promotes a null-secure cookie to Secure on an HTTPS response
 * (Response::prepare()), but that is derived from X-Forwarded-Proto surviving
 * the load balancer. HSTS is the belt to that suspenders — it keeps the browser
 * from ever making the plaintext request in the first place.
 *
 * # Why this is global rather than part of SecurityHeaders
 *
 * SecurityHeaders is registered on the tenant routes and the two Filament
 * panels. The hosts that most need HSTS are not all in that set: Fortify pins
 * no domain (config/fortify.php), so login/password-reset resolve on the
 * central host, the admin host and every tenant subdomain with only the `web`
 * group. A global append also covers Pulse, the health check and anything
 * added later with no extra wiring.
 *
 * SecurityHeaders also deliberately decorates HTML only. HSTS belongs on every
 * response — JSON, redirects and file downloads included — since any of them
 * can be the first HTTPS response a browser sees.
 */
class StrictTransportSecurity
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // RFC 6797 section 7.2: a UA MUST ignore an HSTS header delivered over
        // non-secure transport. Emitting it anyway would be inert in browsers
        // and actively harmful on a plain-HTTP dev box, where a proxy that did
        // honour it would pin the domain to a scheme the app is not serving.
        if (! $request->isSecure()) {
            return $response;
        }

        $maxAge = config()->integer('security.hsts_max_age');

        // A kill switch that needs no deploy: HSTS is cached by the browser for
        // the full max-age, so the one thing this middleware must be able to do
        // in a hurry is stop making the problem bigger.
        if ($maxAge <= 0) {
            return $response;
        }

        $directives = 'max-age='.$maxAge;

        if (config()->boolean('security.hsts_include_subdomains')) {
            $directives .= '; includeSubDomains';
        }

        if (config()->boolean('security.hsts_preload')) {
            $directives .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $directives);

        return $response;
    }
}
