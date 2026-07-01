<?php

use App\Services\Media\TenantAwarePathGenerator;

return [
    /*
     * Scopes media files by tenant, then collection (tenants/{id}/vehicle_photos/{id}/,
     * tenants/{id}/logo/{id}/) instead of Spatie's default bare media-ID folder shared
     * by every tenant. Every other key falls back to the package's own defaults via
     * mergeConfigFrom.
     */
    'path_generator' => TenantAwarePathGenerator::class,
];
