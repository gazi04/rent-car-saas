<?php

return [
    'tooltip' => 'Need help?',
    'fallback_title' => 'Help',
    'fallback_body' => 'Help for this page is coming soon.',

    'templates' => [
        'title' => 'Custom Templates',
        'body' => [
            'Rewrite the rental-agreement terms and the wording of booking emails sent to customers, per language (Albanian/English).',
            'Leave a field blank to fall back to the built-in default text for that field.',
            'Use the placeholders below inside any field — they are replaced with real booking details when the agreement/email is generated.',
        ],
    ],

    'branding' => [
        'title' => 'Branding Settings',
        'body' => [
            'Customize how your public booking site looks: logo, brand colors, font, and the layout of the home/vehicles/vehicle-detail pages.',
            'Content Tab: hero heading, about text, and service blurbs — written per language (Albanian/English), shown on your public site in that language.',
            'Contact & Footer Tab: phone, email, address, footer text, and social links shown across your public site.',
            'Changes apply immediately on save — no republishing step.',
        ],
    ],
];
