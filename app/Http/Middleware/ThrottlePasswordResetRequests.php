<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the `password-reset` named limiter to Fortify's two unauthenticated
 * password-reset POSTs.
 *
 * Fortify ships them with ['guest:web'] and nothing else, and exposes limiter
 * config for login / two-factor / passkeys / verification ONLY — there is no
 * config lever for these (see vendor/laravel/fortify/routes/routes.php).
 *
 * Attaching the throttle to the registered routes from a booted() callback was
 * tried and does not work, in a way worth recording because it fails SILENTLY:
 * RouteServiceProvider::register() queues route loading in a booted callback and
 * the name-lookup refresh in a NESTED one, so a callback queued from any app
 * provider's boot() runs before Route::getRoutes()->getByName() can resolve
 * anything — and under `route:cache` it runs before the routes exist at all.
 * getByName() simply returns null and the throttle is never added.
 *
 * Matching on the route name inside the request lifecycle has no such ordering
 * problem: by the time web-group middleware runs, the route is matched.
 */
class ThrottlePasswordResetRequests
{
    /**
     * Fortify's route names, which are stable across the URI changes
     * config/fortify.php's RoutePath allows.
     *
     * password.update is included as a deliberate extension of the original
     * finding (which named only the reset-link request): it is the same
     * unauthenticated-POST class, and it bounds reset-token brute forcing.
     *
     * @var list<string>
     */
    private const array THROTTLED_ROUTES = ['password.email', 'password.update'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), self::THROTTLED_ROUTES, true)) {
            return $next($request);
        }

        return resolve(ThrottleRequests::class)->handle($request, $next, 'password-reset');
    }
}
