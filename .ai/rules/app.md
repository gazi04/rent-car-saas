---
paths:
  - 'app/**'
---

# App

## Media disk is config-driven (S3 in prod), never hardcode 'public'
Uploaded media (vehicle photos, operator logos) picks its disk from `config()->string('media-library.disk_name')` (env `MEDIA_DISK`, default `public`; Laravel Cloud sets `s3`). Four spots must stay in sync: `Vehicle::registerMediaCollections`, `Tenant::registerMediaCollections`, `VehicleForm` + `BrandingSettings` `SpatieMediaLibraryFileUpload->disk(...)`. Use the typed `config()->string(...)` accessor — bare `config()` is `mixed` and fails phpstan.

Never read media via `Media::getPath()` + `is_file()` — that breaks on S3. Use `App\Services\Media\MediaFileResolver` (`contents()` / `mimeType()` / `dataUri()`), which streams through Media Library's filesystem layer. For dompdf (`enable_remote => false`) inline logos as `dataUri()`; for the AI SDK use `Image::fromBase64(...)` (Pest's `security` arch preset bans `tempnam`, so no temp files).

`config/tenancy.php` keeps `'s3'` commented out of the filesystem bootstrapper disks — `TenantAwarePathGenerator` already prefixes `tenants/{id}/...`; enabling it would double-scope. Conversions are queued, so prod needs a running queue worker.
