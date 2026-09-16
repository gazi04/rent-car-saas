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
     * never a crash — the operator panel gets the operator-facing "sign in" copy, the
     * storefront gets customer-facing copy.
     *
     * The audience is declared at registration (`EnsureTenantIsActive::class.':panel'`
     * in OperatorPanelProvider, bare in routes/tenant.php) rather than sniffed from a
     * `filament.` route-name prefix, which also matched the admin panel and was the only
     * place in the app inferring panel identity from a route name.
     *
     * Livewire's persistent-middleware allowlist compares on the bare class name
     * (`Str::before($value, ':')`), so the parameterised form still replays on the
     * shared update route — see TenancyServiceProvider.
     */
    public function handle(Request $request, Closure $next, string $audience = 'public'): Response
    {
        $tenant = Tenant::current();

        if ($tenant instanceof Tenant && $tenant->status !== TenantStatus::Active) {
            $view = $audience === 'panel' ? 'tenant.inactive' : 'public.unavailable';

            return response()->view($view, [
                'status' => $tenant->status->value,
                'name' => $tenant->name,
            ], 403);
        }

        return $next($request);
    }
}
