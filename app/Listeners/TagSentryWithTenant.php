<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Tenant;
use Sentry\State\Scope;
use Stancl\Tenancy\Events\TenancyInitialized;
use Throwable;

use function Sentry\configureScope;

/**
 * Tags every Sentry event with the tenant it happened under.
 *
 * Without this the issue stream is a single undifferentiated pile: one operator's
 * broken storefront looks identical to a platform-wide outage, and there is no way
 * to answer "is this one tenant or all of them?" — the first question worth asking
 * in a multi-tenant app.
 *
 * Only the tenant id and name are attached — both already loaded on the event, so
 * this costs no query. The host that was hit is already on the event's request
 * context, so there is no need to look the domain up. Nothing here is customer
 * data; see the PII note in config/sentry.php.
 *
 * Registered automatically via Laravel's listener discovery (the handle()
 * type-hint) — do NOT also add it to TenancyServiceProvider's event map, or the
 * scope is configured twice per request. Same convention as RecordAiUsage.
 */
class TagSentryWithTenant
{
    public function handle(TenancyInitialized $event): void
    {
        $tenant = $event->tenancy->tenant;

        // No DSN means the SDK is a no-op, so skip the work entirely rather than
        // paying for it on every tenant request in local and test runs.
        if (! $tenant instanceof Tenant || blank(config('sentry.dsn'))) {
            return;
        }

        try {
            configureScope(function (Scope $scope) use ($tenant): void {
                $scope->setTag('tenant_id', (string) $tenant->id);
                $scope->setTag('tenant_name', $tenant->name);
            });
        } catch (Throwable) {
            // Observability must never break the request it is observing.
        }
    }
}
