<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    /**
     * Gate both the operator panel and the public storefront by tenant status. Runs after
     * tenancy is initialized, so tenant() is set. Inactive tenants get a clear message,
     * never a crash — Filament panel routes get the operator-facing "sign in" copy,
     * everything else gets customer-facing copy.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if ($tenant !== null && $tenant->status !== TenantStatus::Active) {
            $view = str_starts_with((string) $request->route()?->getName(), 'filament.')
                ? 'tenant.inactive'
                : 'public.unavailable';

            return response()->view($view, [
                'status' => $tenant->status->value,
                'name' => $tenant->name,
            ], 403);
        }

        return $next($request);
    }
}
