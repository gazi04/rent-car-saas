<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $middleware->trustProxies(at: '*');

        // Marker group for stancl/tenancy "universal" routes — routes that must work
        // on both central and tenant domains (e.g. the shared Livewire update endpoint).
        // The group itself is empty; its presence is what UniversalRoutes detects.
        $middleware->group('universal', []);

        $middleware->alias([
            'set-locale' => SetLocale::class,
        ]);
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
    })->create();
