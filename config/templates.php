<?php

/*
 * Custom contract / email templates (operator-feature-report.md #7).
 *
 * Operators may rewrite the rental-agreement terms and the customer booking-email
 * wording, per language, with {variable} placeholders. Overrides live in
 * tenant_settings (keyed below, one row per locale); an empty/unset override
 * falls back to the built-in bilingual default (lang/{en,sq}/{contract,emails}.php).
 * Gated by PlanFeature::Templates.
 */

$emails = ['received', 'confirmed', 'rejected', 'cancelled'];
$emailFields = ['subject', 'intro', 'outro'];
$agreementFields = ['terms'];
$locales = ['sq', 'en'];

/*
 * Flat allow-list of tenant_settings keys this feature may write. Anything not
 * here is rejected by Tenant::setSetting(). Keys are the base template key +
 * a locale suffix, resolved back with Tenant::localizedSetting().
 */
$keys = [];

foreach ($agreementFields as $field) {
    foreach ($locales as $locale) {
        $keys[] = "tmpl_agreement_{$field}_{$locale}";
    }
}

foreach ($emails as $event) {
    foreach ($emailFields as $field) {
        foreach ($locales as $locale) {
            $keys[] = "tmpl_email_{$event}_{$field}_{$locale}";
        }
    }
}

return [
    'emails' => $emails,
    'email_fields' => $emailFields,
    'agreement_fields' => $agreementFields,
    'locales' => $locales,

    /*
     * Placeholder tokens an operator can drop into any template. Rendered from
     * the booking by App\Services\TemplateRenderer::bookingVariables().
     */
    'variables' => [
        'customer_name',
        'reference',
        'vehicle',
        'start_date',
        'end_date',
        'total',
        'deposit',
        'operator',
        'pickup_location',
    ],

    'keys' => $keys,
];
