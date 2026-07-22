<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Tenant;
use App\Models\Vehicle;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Groups media files by tenant, then collection name, instead of Spatie's default
 * bare media-ID folder shared by every tenant. The `public` disk is deliberately
 * excluded from stancl/tenancy's FilesystemTenancyBootstrapper (config/tenancy.php's
 * filesystem.disks list) so that storage:link + standard /storage URLs keep working
 * across all tenant subdomains — meaning nothing else scopes this disk by tenant, so
 * the tenant segment has to be added here explicitly.
 */
class TenantAwarePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        return 'tenants/'.$this->resolveTenantId($media).'/'.$media->collection_name.'/'.$media->getKey();
    }

    /**
     * The Tenant model IS the tenant (its own `id` is the tenant key); every other
     * media-owning model (e.g. Vehicle) belongs to a tenant via `tenant_id`.
     */
    protected function resolveTenantId(Media $media): string
    {
        $model = $media->model;

        return match (true) {
            $model instanceof Tenant => (string) $model->id,
            $model instanceof Vehicle => (string) $model->tenant_id,
            default => throw new RuntimeException(
                'No tenant-id resolution rule for media model type ['.$media->model_type.'].'
            ),
        };
    }
}
