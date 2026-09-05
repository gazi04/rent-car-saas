<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveFilamentPanelForSharedRoutes;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events\BootstrappingTenancy;
use Stancl\Tenancy\Events\CreatingDomain;
use Stancl\Tenancy\Events\CreatingTenant;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\DatabaseRolledBack;
use Stancl\Tenancy\Events\DatabaseSeeded;
use Stancl\Tenancy\Events\DeletingDomain;
use Stancl\Tenancy\Events\DeletingTenant;
use Stancl\Tenancy\Events\DomainCreated;
use Stancl\Tenancy\Events\DomainDeleted;
use Stancl\Tenancy\Events\DomainSaved;
use Stancl\Tenancy\Events\DomainUpdated;
use Stancl\Tenancy\Events\EndingTenancy;
use Stancl\Tenancy\Events\InitializingTenancy;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\RevertingToCentralContext;
use Stancl\Tenancy\Events\SavingDomain;
use Stancl\Tenancy\Events\SavingTenant;
use Stancl\Tenancy\Events\SyncedResourceChangedInForeignDatabase;
use Stancl\Tenancy\Events\SyncedResourceSaved;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Events\TenantSaved;
use Stancl\Tenancy\Events\TenantUpdated;
use Stancl\Tenancy\Events\UpdatingDomain;
use Stancl\Tenancy\Events\UpdatingTenant;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Stancl\Tenancy\Listeners\UpdateSyncedResource;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * @return array<class-string, array<int, mixed>>
     */
    public function events(): array
    {
        return [
            // Tenant events
            // Single-database tenancy: no per-tenant database is created, migrated, or
            // deleted, so the database-provisioning job pipelines are intentionally omitted.
            CreatingTenant::class => [],
            TenantCreated::class => [],
            SavingTenant::class => [],
            TenantSaved::class => [],
            UpdatingTenant::class => [],
            TenantUpdated::class => [],
            DeletingTenant::class => [],
            TenantDeleted::class => [],

            // Domain events
            CreatingDomain::class => [],
            DomainCreated::class => [],
            SavingDomain::class => [],
            DomainSaved::class => [],
            UpdatingDomain::class => [],
            DomainUpdated::class => [],
            DeletingDomain::class => [],
            DomainDeleted::class => [],

            // Database events
            DatabaseCreated::class => [],
            DatabaseMigrated::class => [],
            DatabaseSeeded::class => [],
            DatabaseRolledBack::class => [],
            DatabaseDeleted::class => [],

            // Tenancy events
            InitializingTenancy::class => [],
            TenancyInitialized::class => [
                BootstrapTenancy::class,
            ],

            EndingTenancy::class => [],
            TenancyEnded::class => [
                RevertToCentralContext::class,
            ],

            BootstrappingTenancy::class => [],
            TenancyBootstrapped::class => [],
            RevertingToCentralContext::class => [],
            RevertedToCentralContext::class => [],

            // Resource syncing
            SyncedResourceSaved::class => [
                UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->makeLivewireUpdateRouteTenancyAware();
        $this->makeLivewireUpdatesRespectTenantBoundaries();
    }

    /**
     * Livewire's update endpoint is a single global route shared by every panel and
     * page. By default it carries no tenancy middleware, so AJAX requests on an
     * operator subdomain (e.g. the Filament login form) run with tenancy uninitialized,
     * which breaks tenant-scoped logic such as the operator panel access gate.
     *
     * Re-register it as a "universal" route: tenancy is initialized on tenant domains
     * and skipped on central domains (admin panel, central pages), so all three keep working.
     *
     * ResolveFilamentPanelForSharedRoutes additionally sets the current Filament panel —
     * Filament's own SetUpPanel middleware never runs on this shared route, so without it
     * Filament::getCurrentOrDefaultPanel() silently falls back to the default (admin) panel,
     * breaking the operator login's canAccessPanel() check.
     */
    protected function makeLivewireUpdateRouteTenancyAware(): void
    {
        Livewire::setUpdateRoute(fn (array $handle, string $path) => Route::post($path, $handle)
            ->middleware([
                'web',
                'universal',
                InitializeTenancyByDomain::class,
                ResolveFilamentPanelForSharedRoutes::class,
            ]));
    }

    /**
     * Livewire's update endpoint is one global route with no domain constraint, and
     * UniversalRoutes makes tenant identification *skip* rather than 404 on central
     * domains. TenantScope::apply() no-ops when tenancy is uninitialized, so a
     * component re-rendered on a central host queries with no tenant filter at all —
     * a captured storefront snapshot POSTed to the marketing host re-renders with
     * every tenant's rows, no authentication required.
     *
     * On update, Livewire re-applies "persistent middleware": it rebuilds a request
     * from the snapshot's `path` memo against the *current* host, matches it to a live
     * route, and runs that route's middleware that appear in this allowlist. The
     * tenant routes already carry the right guards — they were simply never replayed.
     *
     * Registering them here is precise because the middleware is applied per matched
     * route: public and operator routes carry PreventAccessFromCentralDomains, so a
     * replay on a central host 404s; the admin panel's routes do not carry it, so the
     * admin panel — which shares this very endpoint — is unaffected.
     *
     * InitializeTenancyByDomain is deliberately NOT listed: it already runs as route
     * middleware on the update route, and listing it would initialize tenancy twice.
     */
    protected function makeLivewireUpdatesRespectTenantBoundaries(): void
    {
        Livewire::addPersistentMiddleware([
            PreventAccessFromCentralDomains::class,
            EnsureTenantIsActive::class,
        ]);
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function (): void {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            PreventAccessFromCentralDomains::class,

            InitializeTenancyByDomain::class,
            InitializeTenancyBySubdomain::class,
            InitializeTenancyByDomainOrSubdomain::class,
            InitializeTenancyByPath::class,
            InitializeTenancyByRequestData::class,
        ];

        $kernel = $this->app->make(Kernel::class);

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
