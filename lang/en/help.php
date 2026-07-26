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

    'reports' => [
        'title' => 'Reports',
        'body' => [
            'Pick a start and end date to see booking counts, revenue, and per-vehicle utilisation for that range.',
            'Revenue only counts active and completed bookings — pending, rejected, and cancelled bookings are excluded.',
            'Utilisation is booked days ÷ days in the range, per vehicle — low percentages point to idle cars worth promoting or repricing.',
            'Export CSV downloads every booking overlapping the selected range, including reference, customer, vehicle, dates, status, and total.',
            'The occupancy heatmap shows which vehicle was taken on which day. Booking colours match the availability calendar, and grey hatching marks dates you blocked manually. The bottom row is fleet demand — how much of your fleet was out that day.',
            'The heatmap and the utilisation table above answer different questions, so their numbers differ on purpose. The heatmap counts calendar days touched (a car out 09:00 Monday to 09:00 Wednesday touches 3 days), while the table counts rental duration (48 hours = 2 days). The heatmap also includes completed bookings so past months are not blank, while the table counts only pending, confirmed, and active ones.',
        ],
    ],

    'promo_codes' => [
        'title' => 'Promo Codes',
        'body' => [
            'Create discount codes customers can enter during booking — either a fixed amount off or a percentage off the total.',
            'Set an expiry date and/or a usage limit to control how long and how often a code can be redeemed; leave either blank for no limit.',
            'Only one promo code can be applied per booking — codes do not stack with each other.',
            'Deactivating a code stops new redemptions immediately without deleting its history.',
        ],
    ],

    'service_records' => [
        'title' => 'Service & Maintenance Records',
        'body' => [
            'Log maintenance and service history per vehicle — free on every plan, with no reminders.',
            'On plans with maintenance reminders, a due/overdue service can automatically block the vehicle\'s calendar until it is marked serviced, preventing a booking from starting on an unsafe vehicle.',
            'Marking a record complete removes any auto-block it created for that service.',
        ],
    ],

    'vehicles' => [
        'title' => 'Fleet & Vehicles',
        'body' => [
            'Set hourly, daily, weekly, and monthly rates per vehicle — the booking engine picks the cheapest applicable rate for the requested duration.',
            'Custom fields let you record extra vehicle details (e.g. mileage, transmission) that show up on the public listing without needing a code change.',
            'Photos are converted to WebP automatically and the first uploaded photo is used as the cover image on the public site.',
            'Your plan caps the number of vehicles you can list — the "Add vehicle" button disables itself once you hit that limit.',
        ],
    ],

    'staff' => [
        'title' => 'Staff Accounts',
        'body' => [
            'Add front-desk staff accounts so employees can manage bookings and the fleet without sharing your owner login.',
            'Staff accounts cannot access billing, branding, or plan settings — those stay owner-only.',
            'Your plan caps the number of staff seats — the "Add staff" button disables itself once you hit that limit.',
        ],
    ],

    'customers' => [
        'title' => 'Customers',
        'body' => [
            'Every completed booking automatically creates or updates a customer record here, keyed by phone number.',
            'Use this list to look up a renter\'s booking history before confirming a new reservation.',
            'Editing a customer here does not change any of their past bookings — those keep the details recorded at the time.',
        ],
    ],

    'reviews' => [
        'title' => 'Reviews',
        'body' => [
            'Customers leave a review via a signed, tokenless link emailed after their rental — no account required on their end.',
            'Reviews only appear on your public site once you approve them here; nothing is shown automatically.',
            'A review can only be submitted once per booking.',
        ],
    ],

    'bookings' => [
        'title' => 'Bookings',
        'body' => [
            'Bookings move through a lifecycle: Pending → Confirmed → Active → Completed, or Rejected/Cancelled at any point before Active.',
            'Use "Confirm" once you\'ve checked availability and payment arrangements with the customer, and "Reject" if you can\'t fulfil the request.',
            'Mark a booking "Active" at handover and "Completed" at return — this is what drives revenue and utilisation in Reports.',
            'You can also create a booking manually here for phone/walk-in customers, without them going through the public site.',
        ],
    ],

    'availability_calendar' => [
        'title' => 'Availability Calendar',
        'body' => [
            'Shows every booking and manually blocked date range, color-coded by status — click a booking to open it.',
            'Use "Block dates" (or drag-select a range) to take a vehicle off the public site temporarily, e.g. for maintenance or personal use.',
            'Blocked dates prevent new public bookings from overlapping them, the same way an existing booking does.',
            'Filter by vehicle using the dropdown to see one car\'s schedule at a time.',
        ],
    ],

    'needs_attention' => [
        'title' => 'Needs Attention',
        'body' => [
            'Lists bookings that need a decision from you right now: those still Pending confirmation, and Active rentals whose return date has already passed (overdue).',
            'Click any row to open the booking and act on it — confirm, reject, or mark it returned.',
            'This list clears itself as bookings move past Pending/overdue — no manual dismissal needed.',
        ],
    ],

    'business_summary' => [
        'title' => 'AI Business Summary',
        'body' => [
            'Generates a short written summary of your recent bookings and revenue using AI, so you get a quick read on how business is going without digging through Reports.',
            '"Generate now" reruns the summary on demand for the configured period — each generation replaces the previous one shown here.',
            'This feature is only available on plans that include it.',
        ],
    ],

    'waitlist' => [
        'title' => 'Waitlist',
        'body' => [
            'People who wanted one of your vehicles on dates that were already taken, in the order they asked. Your booking calendar hides taken dates, so without this you would never hear about this demand at all.',
            'When a booking is cancelled or rejected, the earliest person waiting for those dates is emailed automatically. People waiting for dates that do not overlap each other are all emailed, since they are not competing for the same slot.',
            'Being notified does not reserve the vehicle — whoever books first gets it. If the first person never books, the next in line is offered the dates on the following day\'s sweep.',
            'This list also holds stock alerts, shown as "Waiting for the vehicle": people who wanted a vehicle that was off the road and asked to hear when it came back. Everyone waiting is emailed at once the moment you mark that vehicle available again, since a returning vehicle is free for any dates and nobody is competing for a slot.',
            'Entries are added from your public site only; you cannot create them here. Delete any you no longer want to keep.',
        ],
    ],

    'concierge' => [
        'title' => 'FAQ Concierge',
        'body' => [
            'A chat widget on your public site that answers visitor questions — deposit, cancellation, required documents, opening hours — using only the FAQ text you write here. It never invents policy: when the answer is not in your text it tells the visitor to contact you directly.',
            'Write each policy in plain language, in Albanian and/or English. The more you cover, the more the assistant can answer on its own; leave a language blank to skip it. With no FAQ text in either language, the widget does not appear at all.',
            'Answers are only as good as what you write, so keep this accurate and up to date — a stale deposit figure here is what a visitor will be told.',
            'This feature is only available on plans that include it.',
        ],
    ],
];
