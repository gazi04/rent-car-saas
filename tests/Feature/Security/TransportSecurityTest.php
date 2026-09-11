<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Env;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| Transport security: HSTS, cookie Secure flag, admin-panel headers
|--------------------------------------------------------------------------
|
| SESSION_DOMAIN is parent-scoped so operator impersonation survives the
| redirect from the admin host to a tenant subdomain. One cookie is therefore
| valid on every operator-controlled subdomain, and the same config governs the
| long-lived remember-me cookie. Everything here defends that one cookie's
| transport.
|
| The cookie's Secure flag was NOT simply missing before this suite existed:
| Symfony's Response::prepare() promotes a null-secure cookie to Secure on any
| HTTPS response, and Laravel calls prepare() on every routed response
| (Router::toResponse). What was missing is that the guarantee was *derived*
| from X-Forwarded-Proto surviving the load balancer, so it could regress in
| silence. config/session.php now declares it, and these tests pin the declared
| behaviour rather than re-testing the framework's.
|
| An https:// request URL is what makes $request->isSecure() true here — no
| proxy headers needed, which is deliberate: it keeps these tests independent of
| the TrustProxies configuration they are partly insuring against.
|
*/

it('sends HSTS on an https storefront response', function () {
    Tenant::factory()->withDomain('hstson')->create();

    $hsts = test()->get('https://'.tenant_domain('hstson').'/')
        ->assertOk()
        ->headers->get('Strict-Transport-Security');

    // toBeString() first so a missing header fails as 'expected null to be
    // string' rather than an unreadable expectation-type error.
    expect($hsts)->toBeString()
        ->and($hsts)->toContain('max-age='.config('security.hsts_max_age'))
        // includeSubDomains matters specifically because the session cookie is
        // parent-scoped: a plain-HTTP subdomain could otherwise set a cookie the
        // parent domain honours.
        ->and($hsts)->toContain('includeSubDomains')
        // preload is a one-way door; it must stay opt-in.
        ->and($hsts)->not->toContain('preload');
})->group('security');

it('does not send HSTS over plaintext http', function () {
    Tenant::factory()->withDomain('hstsoff')->create();

    // RFC 6797 section 7.2: a UA must ignore HSTS delivered over non-secure
    // transport. Sending it anyway would also pin a local http:// dev domain to
    // a scheme the app is not serving.
    $response = test()->get(tenant_url('hstsoff', '/'))->assertOk();

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
})->group('security');

it('sends HSTS on the admin host too', function () {
    // The whole reason the middleware is registered globally rather than inside
    // SecurityHeaders: the admin host, the central host and Fortify's
    // domain-less auth routes are not in SecurityHeaders' reach. This test goes
    // red if anyone moves it there.
    $hsts = test()->get('https://'.config('tenancy.admin_domain').'/login')
        ->headers->get('Strict-Transport-Security');

    expect($hsts)->toBeString()
        ->and($hsts)->toContain('max-age=');
})->group('security');

it('stops sending HSTS when max-age is zeroed', function () {
    // HSTS is cached by the browser for the full max-age, so the one thing this
    // must support without a code deploy is switching off.
    config()->set('security.hsts_max_age', 0);

    Tenant::factory()->withDomain('hstskill')->create();

    $response = test()->get('https://'.tenant_domain('hstskill').'/')->assertOk();

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
})->group('security');

it('sends clickjacking and sniffing protection on the admin panel', function () {
    // The admin panel had no security headers whatsoever until 2026-09-11, while
    // being the surface that approves, suspends and impersonates tenants.
    // X-Frame-Options is the one that closed a genuinely exploitable path:
    // without it the panel can be framed and a Super Admin clickjacked into a
    // state-changing action.
    $response = test()->get('http://'.config('tenancy.admin_domain').'/login')->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
})->group('security');

it('deliberately sends no CSP on the admin panel', function () {
    // Not an oversight — a regression guard. Filament emits inline scripts
    // (dark-mode bootstrap, sidebar collapse state, window.filamentData) and
    // ships no nonce support, so a script-src without 'unsafe-inline' silently
    // blocks them instead of protecting anything. If someone later "fixes" the
    // asymmetry by dropping ':without-csp', this test explains why not.
    $response = test()->get('http://'.config('tenancy.admin_domain').'/login')->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
})->group('security');

it('still sends the strict CSP on the storefront', function () {
    // The other half of that trade-off: the storefront emits zero inline
    // scripts and is the surface that renders attacker-reachable content, so it
    // keeps the strict policy. Parameterising SecurityHeaders must not have
    // weakened the place the CSP actually matters.
    Tenant::factory()->withDomain('cspkept')->create();

    $csp = test()->get(tenant_url('cspkept', '/'))->assertOk()
        ->headers->get('Content-Security-Policy');

    expect($csp)->toBeString()
        ->and($csp)->toContain("script-src 'self' 'unsafe-eval'")
        ->and($csp)->not->toContain("'unsafe-inline'; script-src")
        ->and($csp)->toContain("frame-ancestors 'none'");
})->group('security');

/**
 * Set or unset an environment variable across all three sources the Dotenv
 * repository reads, in its own precedence order: $_SERVER, then $_ENV, then
 * putenv.
 */
function putEnvEverywhere(string $key, ?string $value): void
{
    if ($value === null) {
        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);

        return;
    }

    $_SERVER[$key] = $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

/**
 * Re-evaluate config/session.php under a mutated environment.
 *
 * Two traps are why this is more than a putenv() call, and both produced
 * green-but-meaningless runs before being found:
 *
 * 1. Env::getRepository() is built immutable (Env.php:89), so its set()/clear()
 *    silently no-op on a variable that already exists. Writing the superglobals
 *    directly is what actually moves the value.
 * 2. The three sources must be captured INDEPENDENTLY. PHPUnit's <env> entries
 *    land in $_ENV and putenv but not $_SERVER, so capturing the original from
 *    $_SERVER alone and restoring to all three deletes APP_ENV outright — which
 *    leaked a broken environment into every later test in the run.
 *
 * @param  array<string, string|null>  $env  null unsets the variable entirely
 */
function sessionSecureUnderEnv(array $env): mixed
{
    $restore = [];

    foreach ($env as $key => $value) {
        $putenv = getenv($key);

        $restore[$key] = [
            'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
            'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
            'putenv' => $putenv === false ? null : $putenv,
        ];

        putEnvEverywhere($key, $value);
    }

    try {
        return (require config_path('session.php'))['secure'];
    } finally {
        foreach ($restore as $key => $sources) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);

            if ($sources['server'] !== null) {
                $_SERVER[$key] = $sources['server'];
            }

            if ($sources['env'] !== null) {
                $_ENV[$key] = $sources['env'];
            }

            if ($sources['putenv'] !== null) {
                putenv("{$key}={$sources['putenv']}");
            }
        }
    }
}

it('defaults the session cookie to Secure outside local and testing', function (?string $appEnv, bool $expected) {
    expect(sessionSecureUnderEnv([
        'APP_ENV' => $appEnv,
        'SESSION_SECURE_COOKIE' => null,
    ]))->toBe($expected);
})->with([
    // Fails closed: an unset APP_ENV must not resolve to an insecure cookie.
    'unset' => [null, true],
    'production' => ['production', true],
    'staging' => ['staging', true],
    'local' => ['local', false],
    'testing' => ['testing', false],
])->group('security');

it('lets a deliberately plaintext environment opt out', function () {
    expect(sessionSecureUnderEnv([
        'APP_ENV' => 'production',
        'SESSION_SECURE_COOKIE' => 'false',
    ]))->toBeFalse();
})->group('security');
