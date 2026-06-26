<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Create the tenant and, in the same flow, its domain row so the operator's
     * subdomain resolves immediately via Step 1 tenancy (mirrors TenantSeeder).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $subdomain = $data['subdomain'];
        unset($data['subdomain']);

        /** @var Tenant $tenant */
        $tenant = static::getModel()::create($data);

        $tenant->domains()->create([
            'domain' => $subdomain.'.'.config('tenancy.tenant_base_domain', 'localhost'),
        ]);

        return $tenant;
    }
}
