<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily release of subdomains held by dead signups (redteam 2026-09-11, finding 4).
 *
 * domains.domain is globally unique and held for as long as the tenant row exists,
 * so a never-approved signup squats its subdomain forever. The admin panel's manual
 * Purge action is the only release path today, which means nothing is ever released
 * unless an admin happens to look.
 *
 * This sweep automates only the subset where no human judgement is owed — see
 * Tenant::autoPurgeable(). Everything else (a Pending signup whose owner verified
 * their email and is waiting on approval) stays with the manual action, because
 * deleting it would punish the prospect for the platform's own backlog.
 *
 * Deletion is per model, never a query delete: the Eloquent path is what fires
 * Tenant's deleting hook (its users) and Media Library's (the logo file). domains,
 * tenant_settings and the rest go with the DB's cascade.
 */
#[Signature('tenants:purge-abandoned')]
#[Description('Permanently delete abandoned signups that never became a business, freeing their subdomains')]
class PurgeAbandonedTenants extends Command
{
    public function handle(): int
    {
        $purged = 0;

        // Resolved to ids first rather than streamed with cursor(): this loop deletes
        // the rows the query is reading, and pluck() also sidesteps stancl's
        // non-generic TenantCollection.
        $ids = Tenant::autoPurgeable()->pluck('id');

        foreach ($ids as $id) {
            $tenant = Tenant::query()->whereKey($id)->first();

            if ($tenant === null) {
                continue;
            }

            $domains = $tenant->domains->pluck('domain');

            DB::transaction(function () use ($tenant, $domains): void {
                // Logged before the delete: activity() needs the subject row to still
                // exist. Causer is null — ActivitiesTable renders that as "System".
                // A distinct description from the admin's manual 'purged' keeps the
                // two separable in the audit log.
                activity()
                    ->performedOn($tenant)
                    ->withProperties([
                        'domains' => $domains->all(),
                        'status' => $tenant->status->value,
                    ])
                    ->log('auto_purged');

                $tenant->delete();
            });

            $this->warn(sprintf('Purged %s (%s).', $tenant->name, $domains->isEmpty() ? 'no domain' : $domains->implode(', ')));
            $purged++;
        }

        $this->info(sprintf('Purged %d abandoned signups.', $purged));

        return self::SUCCESS;
    }
}
