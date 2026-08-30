<?php

use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Media\MediaFileResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    tenancy()->end();
});

/**
 * The resolver exists so media consumers that used a local filesystem path
 * (`Media::getPath()` + `is_file()`) keep working once media lives on S3. These
 * tests run against a faked `s3` disk to prove the byte access is disk-agnostic.
 */
beforeEach(function () {
    config(['media-library.disk_name' => 's3']);

    $this->tenant = Tenant::factory()->withDomain('resolver')->create();
    tenancy()->initialize($this->tenant);
    Storage::fake('s3');

    $this->resolver = resolve(MediaFileResolver::class);
});

it('reads a conversion off the media disk as bytes', function () {
    $vehicle = Vehicle::factory()->create();
    $vehicle->addMedia(UploadedFile::fake()->image('car.jpg', 800, 600))
        ->toMediaCollection('vehicle_photos');

    // Fresh from the DB — the instance addMedia() returns has a stale
    // generated_conversions array, exactly as a real caller never sees.
    $media = $vehicle->getFirstMedia('vehicle_photos');

    $bytes = $this->resolver->contents($media, 'web');

    expect($bytes)->toBeString()->not->toBe('')
        ->and(strlen($bytes))->toBe((int) Storage::disk('s3')->size($media->getPathRelativeToRoot('web')));
});

it('falls back to the original when the requested conversion does not exist', function () {
    $media = $this->tenant->addMedia(UploadedFile::fake()->image('logo.png', 200, 200))
        ->toMediaCollection('logo');

    // 'nope' is not a registered conversion — resolver must return the original.
    $bytes = $this->resolver->contents($media, 'nope');

    expect($bytes)->toBeString()
        ->and(strlen($bytes))->toBe((int) Storage::disk('s3')->size($media->getPathRelativeToRoot()));
});

it('reports the conversion MIME type, falling back to the original', function () {
    $vehicle = Vehicle::factory()->create();
    $vehicle->addMedia(UploadedFile::fake()->image('car.jpg', 800, 600))
        ->toMediaCollection('vehicle_photos');

    $media = $vehicle->getFirstMedia('vehicle_photos');

    // Conversions are webp here; the original stays image/jpeg.
    expect($this->resolver->mimeType($media, 'web'))->toBe('image/webp')
        ->and($this->resolver->mimeType($media, 'nope'))->toBe('image/jpeg')
        ->and($this->resolver->mimeType($media))->toBe('image/jpeg');
});

it('builds a base64 data URI for a conversion', function () {
    $this->tenant->addMedia(UploadedFile::fake()->image('logo.png', 200, 200))
        ->toMediaCollection('logo');

    $uri = $this->resolver->dataUri($this->tenant->getFirstMedia('logo'), 'thumb');

    expect($uri)->toStartWith('data:image/webp;base64,')
        ->and(base64_decode(explode(';base64,', $uri)[1], true))->not->toBeFalse();
});

it('returns null when the media file is missing from the disk', function () {
    $media = $this->tenant->addMedia(UploadedFile::fake()->image('logo.png', 200, 200))
        ->toMediaCollection('logo');

    Storage::disk('s3')->deleteDirectory("tenants/{$this->tenant->id}");

    expect($this->resolver->contents($media))->toBeNull()
        ->and($this->resolver->dataUri($media))->toBeNull();
});
