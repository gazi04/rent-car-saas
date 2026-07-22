<?php

declare(strict_types=1);

/*
 * AI storefront FAQ concierge (future-feature-ideas.md #4).
 *
 * The operator writes a free-text FAQ knowledge base (deposit, cancellation,
 * required documents, opening hours, …) per language. The public chat widget
 * answers visitor questions strictly from this text and says "contact the
 * operator" when the answer isn't in it. Overrides live in tenant_settings,
 * one row per locale, resolved with Tenant::localizedSetting('faq_content').
 * Gated by PlanFeature::AiConcierge.
 *
 * A dedicated config (rather than appending to config/branding.php) keeps this
 * content out of the Branding-gated settings page: concierge-gated content
 * belongs to the concierge-gated page.
 */

$locales = ['sq', 'en'];

/*
 * Flat allow-list of tenant_settings keys this feature may write. Anything not
 * here is rejected by Tenant::setSetting(). Free text — no settingValueIsValid()
 * entry needed (escaped at render, never reaches the CSS-variable sink).
 */
$keys = [];

foreach ($locales as $locale) {
    $keys[] = 'faq_content_'.$locale;
}

return [
    'locales' => $locales,
    'keys' => $keys,
];
