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
     *
     * Media read back from the database (a Filament upload field or image column
     * rendering an existing photo) arrives without its `model` relation loaded, and
     * touching `$media->model` there is an implicit lazy load — fatal under
     * Model::preventLazyLoading(). loadMissing() is an explicit load, so it resolves
     * the owner without tripping that guard, and is a no-op right after an upload,
     * where Spatie has already set the relation.
     */
    protected function resolveTenantId(Media $media): string
    {
        // The owner's key is the tenant key here, so skip the query entirely.
        if ($media->model_type === Tenant::class) {
            return (string) $media->model_id;
        }

        $model = $media->loadMissing('model')->model;

        return match (true) {
            $model instanceof Vehicle => (string) $model->tenant_id,
            default => throw new RuntimeException(
                'No tenant-id resolution rule for media model type ['.$media->model_type.'].'
            ),
        };
    }
}
