<?php

namespace App\Services\Ai;

use App\Ai\Agents\VehicleListingAgent;
use App\Exceptions\AiRequestFailedException;
use App\Models\Vehicle;
use Laravel\Ai\Files;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Writes a public rental-listing description from the vehicle form's specs and
 * (when the vehicle already exists) up to config('ai.max_photos') photos.
 * Generates in the operator's current panel language; nothing is persisted —
 * the result fills the form field for the operator to review and save.
 */
class VehicleListingWriter
{
    /**
     * @param  array<string, mixed>  $specs  Current vehicle form state.
     *
     * @throws AiRequestFailedException
     */
    public function write(array $specs, ?Vehicle $vehicle, string $locale): string
    {
        $language = $locale === 'sq' ? 'Albanian' : 'English';

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
            $scalar = $value instanceof \BackedEnum ? $value->value : $value;

            if (filled($scalar) && is_scalar($scalar)) {
                $lines[] = "{$key}: {$scalar}";
            }
        }

        $facts = implode("\n", $lines);

        $prompt = "Write a listing description in {$language} for this rental vehicle using only these facts"
            .($vehicle ? ' and the attached photos' : '').":\n{$facts}";

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new VehicleListingAgent)->prompt(
                $prompt,
                attachments: $this->photoAttachments($vehicle),
            );
        } catch (Throwable $e) {
            throw AiRequestFailedException::wrap($e);
        }

        /** @var array{description: string} $result */
        $result = $response->toArray();

        return $result['description'];
    }

    /**
     * Existing vehicle photos as image attachments (webp "web" conversion —
     * smaller than the originals, plenty for describing the car). The SDK
     * handles reading + encoding each local file.
     *
     * @return list<Files\Image>
     */
    private function photoAttachments(?Vehicle $vehicle): array
    {
        if ($vehicle === null) {
            return [];
        }

        $attachments = $vehicle->getMedia('vehicle_photos')
            ->take((int) config('ai.max_photos'))
            ->map(function ($media): ?Files\Image {
                $path = $media->hasGeneratedConversion('web') ? $media->getPath('web') : $media->getPath();

                if (! is_file($path)) {
                    return null;
                }

                return Files\Image::fromPath($path);
            })
            ->filter()
            ->all();

        return array_values($attachments);
    }
}
