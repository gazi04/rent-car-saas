<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Rules\AvailableSubdomain;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Create the tenant and, in the same flow, its domain row so the operator's
     * subdomain resolves immediately via Step 1 tenancy (mirrors TenantSeeder).
     *
     * Wrapped in a transaction because AvailableSubdomain's availability check is a
     * read followed by an insert: if the domain write is rejected — by stancl's own
     * occupancy guard on `saving`, or by the `domains.domain` unique index behind it
     * — the tenant row must not survive as an orphan. The race itself still surfaces
     * to the admin as an error page rather than a form error: rare, admin-only, and
     * not worth threading Filament's `data.`-prefixed error keys for.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var string $subdomain */
        $subdomain = $data['subdomain'];
        unset($data['subdomain']);

        return DB::transaction(function () use ($data, $subdomain): Tenant {
            /** @var Tenant $tenant */
            $tenant = static::getModel()::query()->create($data);

            $tenant->domains()->create([
                'domain' => AvailableSubdomain::fullDomain($subdomain),
            ]);

            return $tenant;
        });
    }
}
