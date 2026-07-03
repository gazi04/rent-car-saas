<?php

namespace App\Services\Ai;

use App\Models\Vehicle;

/**
 * Writes a public rental-listing description from the vehicle form's specs and
 * (when the vehicle already exists) up to config('ai.max_photos') photos.
 * Generates in the operator's current panel language; nothing is persisted —
 * the result fills the form field for the operator to review and save.
 */
class VehicleListingWriter
{
    public function __construct(private readonly AiChatService $chat) {}

    /**
     * @param  array<string, mixed>  $specs  Current vehicle form state.
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

        $content = [
            ...$this->photoParts($vehicle),
            [
                'type' => 'text',
                'text' => "Write a listing description in {$language} for this rental vehicle using only these facts"
                    .($vehicle ? ' and the attached photos' : '').":\n{$facts}",
            ],
        ];

        /** @var array{description: string} $result */
        $result = $this->chat->chat(
            messages: [
                [
                    'role' => 'system',
                    'content' => 'You write short, appealing descriptions for a car-rental booking website. '
                        .'2-3 sentences, plain text, no headings or bullet lists, no price, '
                        .'and never invent features that are not in the provided facts or visible in the photos.',
                ],
                ['role' => 'user', 'content' => $content],
            ],
            jsonSchema: [
                'name' => 'vehicle_listing',
                'schema' => [
                    'type' => 'object',
                    'properties' => ['description' => ['type' => 'string']],
                    'required' => ['description'],
                    'additionalProperties' => false,
                ],
            ],
        );

        return $result['description'];
    }

    /**
     * Existing vehicle photos as base64 image parts (webp "web" conversion —
     * smaller than the originals, plenty for describing the car).
     *
     * @return list<array<string, mixed>>
     */
    private function photoParts(?Vehicle $vehicle): array
    {
        if ($vehicle === null) {
            return [];
        }

        $parts = $vehicle->getMedia('vehicle_photos')
            ->take((int) config('ai.max_photos'))
            ->map(function ($media): ?array {
                $path = $media->hasGeneratedConversion('web') ? $media->getPath('web') : $media->getPath();

                if (! is_file($path)) {
                    return null;
                }

                return [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'data:image/webp;base64,'.base64_encode((string) file_get_contents($path)),
                    ],
                ];
            })
            ->filter()
            ->all();

        return array_values($parts);
    }
}
