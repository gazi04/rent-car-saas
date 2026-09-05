<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottlePasswordResetRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a TLS-terminating proxy/load balancer the app sees plain HTTP;
        // without honoring X-Forwarded-* the signed cancel/agreement links are
        // validated against the wrong scheme and 403. app.url must still match
        // the real public origin (signed URLs are generated from it in queue
        // workers — see Tenant::publicRootUrl()).
        //
        // Laravel Cloud publishes no fixed load-balancer CIDR, so the proxy list
        // stays '*'. X-Forwarded-Host is deliberately EXCLUDED from the trusted
        // header set: InitializeTenancyByDomain resolves the tenant from
        // $request->getHost(), so trusting that header would let anyone able to
        // reach the origin directly (off-LB port, SSRF) pick which tenant a
        // request resolves as. Scheme/port/for stay trusted — the scheme is the
        // whole reason this setting exists.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Marker group for stancl/tenancy "universal" routes — routes that must work
        // on both central and tenant domains (e.g. the shared Livewire update endpoint).
        // The group itself is empty; its presence is what UniversalRoutes detects.
        $middleware->group('universal', []);

        $middleware->alias([
            'set-locale' => SetLocale::class,
        ]);

        // Rate-limits Fortify's password-reset POSTs, which the package ships
        // unthrottled and offers no config lever for. It lives in the web group
        // rather than on the routes themselves because Fortify's routes cannot
        // be reliably mutated after registration — see the middleware's docblock.
        $middleware->appendToGroup('web', ThrottlePasswordResetRequests::class);

        // The Resend delivery webhook is a signed server-to-server POST — it has
        // no session/CSRF token; its Svix signature is verified in the controller.
        $middleware->validateCsrfTokens(except: ['webhooks/resend']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // An unknown subdomain is a missing resource, not a server error.
        $exceptions->render(
            fn (TenantCouldNotBeIdentifiedOnDomainException $e) => abort(404),
        );

        // Never leak a 403 to the browser (e.g. wrong-tenant Filament panel access,
        // tampered/expired signed URLs): a resource you're not allowed to see should
        // look the same as one that doesn't exist.
        $exceptions->render(
            fn (HttpExceptionInterface $e) => $e->getStatusCode() === 403 ? abort(404) : null,
        );

        Integration::handles($exceptions);
    })->create();
