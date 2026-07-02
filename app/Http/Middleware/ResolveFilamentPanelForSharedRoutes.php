<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire's update endpoint is a single global route shared by every panel and page
 * (see TenancyServiceProvider::makeLivewireUpdateRouteTenancyAware()). Filament's own
 * SetUpPanel middleware — which calls Filament::setCurrentPanel() — only runs on each
 * panel's own route group, never on this shared route, so Filament::getCurrentPanel()
 * is null here and getCurrentOrDefaultPanel() silently falls back to whichever panel is
 * marked ->default() (admin). That breaks anything gated on the *current* panel during a
 * request to this route — most importantly, the operator login's canAccessPanel() check.
 * Must run after InitializeTenancyByDomain, so tenancy()->initialized reflects this request.
 */
class ResolveFilamentPanelForSharedRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        Filament::setCurrentPanel(Filament::getPanel(tenancy()->initialized ? 'operator' : 'admin'));
        Filament::bootCurrentPanel();

        return $next($request);
    }
}
