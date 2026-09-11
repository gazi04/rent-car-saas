<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Tenant;

/**
 * Resolves operator-authored contract / email templates (feature #7). An override
 * stored in tenant_settings (per locale) wins over the built-in bilingual default;
 * {variable} placeholders are substituted from the booking. Returns raw text —
 * callers output it through Blade so it is escaped (no HTML/script injection into
 * the PDF or emails).
 */
class TemplateRenderer
{
    /**
     * The override wording for $overrideKey rendered with the booking's variables,
     * or the translated default when the operator has set nothing.
     *
     * @param  array<string, string>  $defaultReplace  Replacements for the default __() string.
     */
    public function resolve(Booking $booking, string $overrideKey, string $defaultLangKey, array $defaultReplace = []): string
    {
        $override = Tenant::query()->find($booking->tenant_id)?->localizedSetting($overrideKey);

        if (filled($override)) {
            return $this->render((string) $override, $this->bookingVariables($booking));
        }

        return (string) __($defaultLangKey, $defaultReplace);
    }

    /**
     * Replace every {key} token in $template with its value. Unknown tokens are
     * left untouched (a visible typo, never a crash).
     *
     * @param  array<string, string>  $vars
     */
    public function render(string $template, array $vars): string
    {
        return str_replace(
            array_map(fn (string $key): string => '{'.$key.'}', array_keys($vars)),
            array_values($vars),
            $template,
        );
    }

    /**
     * The {variable} => value map exposed to templates (config('templates.variables')).
     *
     * @return array<string, string>
     */
    public function bookingVariables(Booking $booking): array
    {
        return [
            'customer_name' => $booking->customer_name,
            'reference' => $booking->reference,
            'vehicle' => $booking->vehicle->name,
            'start_date' => $booking->start_date->format('d M Y'),
            'end_date' => $booking->end_date->format('d M Y'),
            'total' => '€'.number_format((float) $booking->total, 2),
            'deposit' => '€'.number_format((float) $booking->deposit, 2),
            'operator' => Tenant::query()->findOrFail($booking->tenant_id)->name,
            'pickup_location' => $booking->pickup_location ?? '',
        ];
    }
}
