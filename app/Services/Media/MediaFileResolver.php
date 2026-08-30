<?php

declare(strict_types=1);

namespace App\Services\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Reads media bytes through Media Library's own filesystem layer instead of a
 * local path. Works whether media lives on the local `public` disk or a remote
 * S3 bucket — the callers that used `Media::getPath()` + `is_file()` silently
 * lost their image once the disk became S3.
 */
class MediaFileResolver
{
    /**
     * Raw bytes of a media conversion, falling back to the original when the
     * conversion has not been generated. Returns null if the file is missing.
     */
    public function contents(Media $media, string $conversion = ''): ?string
    {
        $conversion = $this->resolveConversion($media, $conversion);

        try {
            $stream = $media->stream($conversion);
        } catch (Throwable) {
            return null;
        }

        if (! is_resource($stream)) {
            return null;
        }

        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents === false ? null : $contents;
    }

    /**
     * MIME type of the bytes `contents()` would return for the same arguments —
     * the conversion's own type (conversions are webp here) when it exists,
     * otherwise the original's stored MIME type.
     */
    public function mimeType(Media $media, string $conversion = ''): string
    {
        return $this->resolveConversion($media, $conversion) !== ''
            ? 'image/'.pathinfo($media->getPath($conversion), PATHINFO_EXTENSION)
            : $media->mime_type;
    }

    /**
     * Base64 `data:` URI for a media conversion — for contexts that cannot fetch
     * a remote URL, such as dompdf (`enable_remote => false`). Null if missing.
     */
    public function dataUri(Media $media, string $conversion = ''): ?string
    {
        $contents = $this->contents($media, $conversion);

        if ($contents === null) {
            return null;
        }

        return 'data:'.$this->mimeType($media, $conversion).';base64,'.base64_encode($contents);
    }

    /**
     * The conversion name if it is a real, generated conversion; '' otherwise
     * (which `Media::stream()` and `getPath()` treat as "the original file").
     */
    private function resolveConversion(Media $media, string $conversion): string
    {
        return ($conversion !== '' && $media->hasGeneratedConversion($conversion)) ? $conversion : '';
    }
}
