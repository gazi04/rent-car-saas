<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Marker group for stancl/tenancy "universal" routes — routes that must work
        // on both central and tenant domains (e.g. the shared Livewire update endpoint).
        // The group itself is empty; its presence is what UniversalRoutes detects.
        $middleware->group('universal', []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // An unknown subdomain is a missing resource, not a server error.
        $exceptions->render(
            fn (TenantCouldNotBeIdentifiedOnDomainException $e) => abort(404),
        );
    })->create();
