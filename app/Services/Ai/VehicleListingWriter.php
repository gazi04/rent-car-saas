<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\VehicleListingAgent;
use App\Exceptions\AiRequestFailedException;
use App\Models\Vehicle;
use BackedEnum;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Writes a public rental-listing description from the vehicle form's specs and
 * (when the vehicle already exists) up to config('ai.max_photos') photos.
 * Generates both English and Albanian in one call; nothing is persisted — the
 * result fills the two form fields for the operator to review and save.
 */
class VehicleListingWriter
{
    /**
     * @param  array<string, mixed>  $specs  Current vehicle form state.
     * @return array{en: string, sq: string}
     *
     * @throws AiRequestFailedException
     */
    public function write(array $specs, ?Vehicle $vehicle): array
    {
        $rawFacts = [
            'name' => $specs['name'] ?? null,
            'category' => $specs['category'] ?? null,
            'year' => $specs['year'] ?? null,
            'fuel type' => $specs['fuel_type'] ?? null,
            'transmission' => $specs['transmission'] ?? null,
            'seats' => $specs['seats'] ?? null,
        ];

        foreach (($specs['custom_fields'] ?? []) as $field) {
            $label = is_array($field) ? (string) ($field['label'] ?? '') : '';

            if ($label !== '') {
                $rawFacts[$label] = $field['value'] ?? null;
            }
        }

        $lines = [];

        foreach ($rawFacts as $key => $value) {
            // Form state may hand us backed enums (category, fuel type, …) —
            // take their scalar value so the prompt is plain text.
            $scalar = $value instanceof BackedEnum ? $value->value : $value;

            if (filled($scalar) && is_scalar($scalar)) {
                $lines[] = sprintf('%s: %s', $key, $scalar);
            }
        }

        $facts = implode("\n", $lines);

        $prompt = 'Write a listing description for this rental vehicle using only these facts'
            .($vehicle instanceof Vehicle ? ' and the attached photos' : '')
            .":\n"
            .$facts;

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new VehicleListingAgent)->prompt(
                $prompt,
                attachments: $this->photoAttachments($vehicle),
            );
        } catch (Throwable $throwable) {
            throw AiRequestFailedException::wrap($throwable);
        }

        /** @var array{en?: string, sq?: string} $result */
        $result = $response->toArray();

        $en = trim($result['en'] ?? '');
        $sq = trim($result['sq'] ?? '');

        if ($en === '' || $sq === '') {
            throw AiRequestFailedException::malformedResponse();
        }

        return ['en' => $en, 'sq' => $sq];
    }

    /**
     * Existing vehicle photos as image attachments (webp "web" conversion —
     * smaller than the originals, plenty for describing the car). The SDK
     * handles reading + encoding each local file.
     *
     * @return list<Image>
     */
    private function photoAttachments(?Vehicle $vehicle): array
    {
        if (! $vehicle instanceof Vehicle) {
            return [];
        }

        $attachments = $vehicle->getMedia('vehicle_photos')
            ->take((int) config('ai.max_photos'))
            ->map(function (Media $media): ?Image {
                $path = $media->hasGeneratedConversion('web') ? $media->getPath('web') : $media->getPath();

                if (! is_file($path)) {
                    return null;
                }

                return Image::fromPath($path);
            })
            ->filter()
            ->all();

        return array_values($attachments);
    }
}
