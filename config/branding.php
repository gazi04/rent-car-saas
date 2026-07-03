<?php

/*
 * Free-text content the operator writes, stored per language as
 * {key}_sq / {key}_en (the base un-suffixed key is kept on the allow-list
 * for values saved before the bilingual split — see Tenant::localizedSetting()).
 */
$localizedContentKeys = [
    'footer_text',
    'home_hero_heading',
    'home_hero_subheading',
    'home_hero_cta_label',
    'home_about_title',
    'home_about_text',
    'home_service_1_title',
    'home_service_1_text',
    'home_service_2_title',
    'home_service_2_text',
    'home_service_3_title',
    'home_service_3_text',
];

$localizedVariants = [];
foreach ($localizedContentKeys as $key) {
    $localizedVariants[] = "{$key}_sq";
    $localizedVariants[] = "{$key}_en";
}

return [
    /*
     * Keys operators may write via setSetting(). Any key NOT in this list
     * is silently rejected — prevents mass-assignment of arbitrary settings.
     */
    'keys' => [
        ...$localizedContentKeys,
        ...$localizedVariants,
        'color_primary',
        'color_secondary',
        'font_family',
        'social_facebook',
        'social_instagram',
        'contact_phone',
        'contact_email',
        'contact_address',
        'payment_instructions',
        'layout_home',
        'layout_vehicles',
        'layout_vehicle_show',
    ],

    /*
     * Content keys that exist per language (used by the branding form to
     * render one field set per locale).
     */
    'localized_keys' => $localizedContentKeys,

    /*
     * Curated font allow-list.  The key is the canonical name stored in
     * tenant_settings; never allow free-text fonts (injection + layout risk).
     */
    'fonts' => [
        'Inter' => [
            'label' => 'Inter',
            'url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        ],
        'Poppins' => [
            'label' => 'Poppins',
            'url' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
        ],
        'Roboto' => [
            'label' => 'Roboto',
            'url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        ],
        'Nunito' => [
            'label' => 'Nunito',
            'url' => 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap',
        ],
        'Lato' => [
            'label' => 'Lato',
            'url' => 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap',
        ],
    ],

    /*
     * Curated page-layout allow-list. Each public page may only use the
     * layouts listed for it; anything else falls back to the default.
     * Slugs map 1:1 to Blade partials in resources/views/pages/public/partials/.
     */
    'layouts' => [
        'home' => [
            'two-column',
            'split-screen',
            'f-shape',
            'z-shape',
            'card-block',
            'asymmetrical',
            'full-screen',
        ],
        'vehicles' => [
            'card-block',
            'two-column',
            'f-shape',
        ],
        'vehicle_show' => [
            'split-screen',
            'two-column',
            'z-shape',
        ],
    ],

    'defaults' => [
        'color_primary' => '#2563eb',
        'color_secondary' => '#1e40af',
        'font_family' => 'Inter',
        'layout_home' => 'full-screen',
        'layout_vehicles' => 'card-block',
        'layout_vehicle_show' => 'split-screen',
    ],
];
