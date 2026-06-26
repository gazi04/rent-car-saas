<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    /**
     * Gate the operator panel by tenant status. Runs after tenancy is initialized,
     * so tenant() is set. Inactive tenants get a clear message (HTTP 200), never a crash.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant !== null && $tenant->status !== 'active') {
            return response()->view('tenant.inactive', [
                'status' => $tenant->status,
                'name' => $tenant->name,
            ]);
        }

        return $next($request);
    }
}
