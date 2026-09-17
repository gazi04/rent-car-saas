<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Budgets FAILED booking-reference lookups per IP.
 *
 * /booking/{reference}/confirmation returns another party's booking on a correct
 * reference, with no authentication: the vehicle, the rental dates, the total paid,
 * and whether an email address is on file. Not full contact details — the page
 * renders none — but a stranger's rental history all the same.
 * The 2026-09-03 review sized its throttle (30/min) against a reference space of
 * `62^6 ≈ 5.7e10`. That figure was wrong: the generator was
 * Str::upper(Str::random(6)), which folds a 62-symbol draw onto 36 non-uniformly
 * and yields ~30.7 bits (~1.7e9). At 30/min across a modest spread of addresses,
 * a tenant with a few thousand bookings is enumerable in days.
 *
 * App\Support\BookingReference now mints 40 bits, but that only helps bookings
 * made from here on — every reference already issued is in a customer's inbox and
 * cannot be reissued. This is what defends those, and it is the half of the fix
 * that is not optional.
 *
 * # Why misses rather than requests
 *
 * A customer refreshing their own confirmation page never 404s, so a budget spent
 * only on failures can be tight without ever touching legitimate use — which is
 * what makes 10/hour tolerable where 10/hour on all requests plainly would not be.
 * An enumerator 404s on every single attempt and is finished almost at once.
 *
 * # Why 404 on exhaustion, not 429
 *
 * Same reasoning as bootstrap/app.php's blanket 403 -> 404 conversion: a 429 tells
 * an attacker a budget exists, roughly how large it is, and when it refills, which
 * is everything needed to pace around it. An indistinguishable 404 leaves them
 * unable to tell a throttle from a wrong guess, so they keep spending effort on
 * attempts that can no longer pay out. It also means a spent budget refuses the
 * CORRECT reference too — guessing does not become cheap again at the moment the
 * attacker finally guesses right.
 *
 * # Why counting is driven from the route's missing() hook
 *
 * This middleware cannot observe a miss itself. Route-model binding fails inside
 * SubstituteBindings, which the tenancy provider's
 * makeTenancyMiddlewareHighestPriority() reordering leaves running BEFORE this
 * middleware — verified, not assumed: with the counting done here, a miss never
 * reached this class at all. SubstituteBindings does, however, hand a binding
 * failure to the route's missing() callback (SubstituteBindings.php:45-46), which
 * is why routes/tenant.php wires that callback to recordMiss(). That hook fires
 * exactly on binding failure and cannot be reordered out from under us.
 */
class ThrottleBookingReferenceMisses
{
    /**
     * Misses allowed per IP per hour.
     *
     * Owned here rather than as a named RateLimiter in AppServiceProvider, because
     * this is not reached through the `throttle:` middleware — it counts only
     * failures, which Laravel's limiter cannot express. One definition beats a
     * registration nothing resolves.
     */
    private const int MAX_MISSES = 10;

    private const int DECAY_SECONDS = 3600;

    private const string KEY_PREFIX = 'booking-reference-misses:';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(RateLimiter::tooManyAttempts(self::key($request), self::MAX_MISSES), 404);

        $response = $next($request);

        // The page can also abort(404) on its own after binding succeeds — a
        // booking belonging to another tenant, say. That is a miss too.
        if ($response->getStatusCode() === 404) {
            self::recordMiss($request);
        }

        return $response;
    }

    /**
     * Charge one failed lookup against this IP's budget.
     *
     * Called from the route's missing() callback, which is the only place a
     * binding failure is observable without depending on middleware ordering.
     */
    public static function recordMiss(Request $request): void
    {
        RateLimiter::hit(self::key($request), self::DECAY_SECONDS);
    }

    /**
     * Keyed on IP alone, not IP+tenant, deliberately: an enumerator walking every
     * subdomain in turn is one attacker, and per-tenant keys would let them reset
     * the budget by switching host.
     */
    private static function key(Request $request): string
    {
        return self::KEY_PREFIX.$request->ip();
    }
}
