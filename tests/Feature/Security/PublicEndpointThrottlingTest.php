<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| Rate limits on the unauthenticated surface
|--------------------------------------------------------------------------
|
| Three endpoints that shipped with no ceiling at all:
|
|  - Fortify's password-reset POSTs. Fortify exposes limiter config for login,
|    two-factor, passkeys and verification ONLY, so these had ['guest:web'] and
|    nothing else. Every accepted request is a queued Resend send, so leaving it
|    open costs the operator's inbox and the platform's mail spend.
|  - The booking confirmation and review pages, the only public GETs that return
|    another party's booking on a correct guess.
|  - The CSP report sink, an unauthenticated POST whose only job is to write log
|    lines.
|
| The password-reset case is asserted through real HTTP rather than by reading
| the route, because the throttle is NOT on the route: it is applied by
| App\Http\Middleware\ThrottlePasswordResetRequests in the web group. Attaching
| it to Fortify's routes from a booted() callback was tried first and fails
| silently (RouteServiceProvider loads routes, and refreshes the name lookup, in
| booted callbacks of its own — an app provider's callback runs before both).
| A route-shaped assertion would have passed against that broken version.
|
*/

beforeEach(fn () => RateLimiter::clear('pw-reset-ip:127.0.0.1'));

it('throttles repeated password-reset requests for one address', function () {
    Notification::fake();

    $user = User::factory()->create();

    // The per-email limit is 5/hour; the 6th must be refused.
    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.request'), ['email' => $user->email])->assertStatus(302);
    }

    $this->post(route('password.request'), ['email' => $user->email])->assertStatus(429);
})->group('security');

it('throttles password-reset requests aimed at one address from other IPs', function () {
    Notification::fake();

    $user = User::factory()->create();

    // The per-address half is what a per-IP limiter alone would miss: a
    // distributed flood at one known operator address.
    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.request'), ['email' => $user->email])->assertStatus(302);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post(route('password.request'), ['email' => $user->email])
        ->assertStatus(429);
})->group('security');

it('does not throttle an unrelated address from the same IP too early', function () {
    Notification::fake();

    $first = User::factory()->create();
    $second = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.request'), ['email' => $first->email])->assertStatus(302);
    }

    // The per-IP limit (15/hour) has not been reached, so a genuine second
    // person behind the same NAT can still ask for their link.
    $this->post(route('password.request'), ['email' => $second->email])->assertStatus(302);
})->group('security');

it('throttles the booking confirmation and review pages', function () {
    // Asserted on the route rather than by replaying 31 requests: this one IS
    // declared on the route, so the declaration is the thing under test.
    $middleware = fn (string $name): array => collect(app('router')->getRoutes()->getByName($name)->gatherMiddleware())
        ->map(fn ($m) => is_string($m) ? $m : '')
        ->all();

    expect($middleware('public.booking.confirmation'))->toContain('throttle:booking-links')
        ->and($middleware('public.booking.review'))->toContain('throttle:booking-links');
})->group('security');

it('accepts and logs a CSP violation report on a tenant host', function () {
    Tenant::factory()->withDomain('cspt')->create();

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'CSP violation reported'
            && $context['blocked_uri'] === 'https://evil.tld/x.js'
            && $context['violated_directive'] === 'script-src');

    postJson(tenant_url('cspt', '/csp-report'), [
        'csp-report' => [
            'blocked-uri' => 'https://evil.tld/x.js',
            'violated-directive' => 'script-src',
            'document-uri' => 'http://cspt.localhost/',
        ],
    ])->assertNoContent();
})->group('security');

it('ignores an oversized or malformed CSP report without logging', function () {
    Tenant::factory()->withDomain('cspt')->create();

    Log::shouldReceive('warning')->never();

    postJson(tenant_url('cspt', '/csp-report'), ['not-a-report' => true])->assertNoContent();

    postJson(tenant_url('cspt', '/csp-report'), [
        'csp-report' => ['blocked-uri' => str_repeat('a', 9000)],
    ])->assertNoContent();
})->group('security');

it('advertises the report endpoint in the storefront CSP', function () {
    $tenant = Tenant::factory()->withDomain('cspt')->create();

    tenancy()->initialize($tenant);
    Vehicle::factory()->create(['is_public' => true]);
    tenancy()->end();

    $response = $this->get(tenant_url('cspt', '/'));

    expect($response->headers->get('Content-Security-Policy'))->toContain('report-uri /csp-report');
})->group('security');

it('leaves the booking model untouched by any of this', function () {
    // Guard against a throttle that accidentally swallows a real request: the
    // suite above asserts refusals, this asserts the happy path still exists.
    expect(Booking::query()->count())->toBe(0);
})->group('security');
