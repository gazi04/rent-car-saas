<?php

return [
    /*
     * Keys operators may write via setSetting(). Any key NOT in this list
     * is silently rejected — prevents mass-assignment of arbitrary settings.
     */
    'keys' => [
        'color_primary',
        'color_secondary',
        'font_family',
        'footer_text',
        'social_facebook',
        'social_instagram',
        'contact_phone',
        'contact_email',
        'contact_address',
        'payment_instructions',
    ],

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

    'defaults' => [
        'color_primary' => '#2563eb',
        'color_secondary' => '#1e40af',
        'font_family' => 'Inter',
    ],
];
