<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Operator\Resources\Staff\Pages\CreateStaff;
use App\Filament\Operator\Resources\Staff\Pages\ListStaff;
use App\Filament\Operator\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Operator\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Support\PlanLimit;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\SetUserLocale;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class OperatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operator')
            // No fixed ->domain(): this single panel serves every tenant subdomain.
            // The tenancy middleware resolves which tenant per request.
            ->path('dashboard')
            ->viteTheme('resources/css/filament/operator/theme.css')
            ->login()
            ->brandName(function (): string {
                $tenant = Tenant::current();

                return $tenant === null ? 'Operator' : $tenant->name;
            })
            ->colors([
                'primary' => Color::Indigo,
            ])
            // Enables FullCalendar's drag-across-days selection; AvailabilityCalendar's
            // onDateSelect() relies on this to open the block-dates modal.
            ->plugin(FilamentFullCalendarPlugin::make()->selectable())
            // Language toggle: shows the *other* language, mirroring the public
            // site's toggle convention. Persists to users.locale via the route.
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn (): string => app()->getLocale() === 'sq' ? 'English' : 'Shqip')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('operator.locale', [
                        'locale' => app()->getLocale() === 'sq' ? 'en' : 'sq',
                    ])),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Always-visible "you've hit your plan's cap" banner at the top of the
            // vehicle/staff list + create pages. Re-evaluated on every render, so
            // it shows the instant the cap is reached — including a direct-URL
            // load of the create page and the post-"create another" form reset.
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => PlanLimit::vehiclesReached()
                    ? view('filament.operator.partials.plan-limit-banner', [
                        'title' => __('panel.vehicle_limit_reached_title'),
                        'body' => __('panel.vehicle_limit_reached_body', ['limit' => PlanLimit::vehicleLimit()]),
                    ])->render()
                    : '',
                scopes: [ListVehicles::class, CreateVehicle::class],
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => PlanLimit::staffSeatsReached()
                    ? view('filament.operator.partials.plan-limit-banner', [
                        'title' => __('panel.staff_seat_limit_reached_title'),
                        'body' => __('panel.staff_seat_limit_reached_body', ['limit' => PlanLimit::staffSeatLimit()]),
                    ])->render()
                    : '',
                scopes: [ListStaff::class, CreateStaff::class],
            )
            ->discoverResources(in: app_path('Filament/Operator/Resources'), for: 'App\Filament\Operator\Resources')
            ->discoverPages(in: app_path('Filament/Operator/Pages'), for: 'App\Filament\Operator\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Operator/Widgets'), for: 'App\Filament\Operator\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SetUserLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Tenancy: resolve the tenant from the subdomain, block central
                // domains, then ensure the tenant is active before the panel loads.
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
                EnsureTenantIsActive::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
