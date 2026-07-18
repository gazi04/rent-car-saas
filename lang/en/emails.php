<?php

return [
    // Booking received (customer)
    'booking_received' => [
        'subject' => 'Booking Request Received — :reference',
        'greeting' => 'Hello :name,',
        'intro' => 'We have received your booking request. The operator will review it and confirm shortly.',
        'reference_label' => 'Reference',
        'vehicle_label' => 'Vehicle',
        'dates_label' => 'Dates',
        'total_label' => 'Total',
        'cancel_action' => 'Cancel Booking',
        'cancel_note' => 'You can cancel this booking within 24 hours using the link above.',
        'outro' => 'Thank you for choosing :operator.',
    ],

    // New booking alert (operator)
    'new_booking_alert' => [
        'subject' => 'New Booking Request — :reference',
        'bell_title' => 'New Booking Request',
        'greeting' => 'Hello,',
        'intro' => 'A new booking request has been submitted.',
        'customer_label' => 'Customer',
        'vehicle_label' => 'Vehicle',
        'dates_label' => 'Dates',
        'total_label' => 'Total',
        'view_action' => 'View Booking',
        'outro' => 'Log in to your dashboard to confirm or reject this booking.',
    ],

    // Booking confirmed (customer)
    'booking_confirmed' => [
        'subject' => 'Booking Confirmed — :reference',
        'greeting' => 'Hello :name,',
        'intro' => 'Great news — your booking has been confirmed!',
        'reference_label' => 'Reference',
        'vehicle_label' => 'Vehicle',
        'dates_label' => 'Dates',
        'total_label' => 'Total',
        'payment_note' => 'Payment is due on pickup — please arrange this with the operator.',
        'agreement_button' => 'Download Agreement',
        'outro' => 'Thank you for booking with :operator.',
    ],

    // Booking rejected (customer)
    'booking_rejected' => [
        'subject' => 'Booking Update — :reference',
        'greeting' => 'Hello :name,',
        'intro' => 'Unfortunately, the operator was unable to confirm your booking request.',
        'reference_label' => 'Reference',
        'vehicle_label' => 'Vehicle',
        'dates_label' => 'Dates',
        'outro' => 'Please try booking different dates or contact :operator directly.',
    ],

    // Booking cancelled (customer or operator)
    'booking_cancelled' => [
        'subject' => 'Booking Cancelled — :reference',
        'bell_title' => 'Booking Cancelled by Customer',
        'greeting' => 'Hello :name,',
        'intro' => 'Your booking has been cancelled.',
        'reference_label' => 'Reference',
        'vehicle_label' => 'Vehicle',
        'dates_label' => 'Dates',
        'outro' => 'If you did not request this cancellation, please contact :operator.',
    ],

    // Subscription renewal reminder (operator, manual B2B billing)
    'subscription_renewal_reminder' => [
        'subject' => 'Your subscription renews in :days day(s)',
        'greeting' => 'Hello :name,',
        'intro' => 'Your subscription period ends in :days day(s), on :date.',
        'plan_label' => 'Plan',
        'paid_until_label' => 'Paid until',
        'payment_note' => 'To keep your booking site and dashboard active, please arrange the payment by bank transfer or in person before the period ends.',
        'outro' => 'If you have already paid, you can ignore this message — your payment will be recorded shortly.',
    ],

    // Trial expiring reminder (operator, first free period)
    'trial_expiring_reminder' => [
        'subject' => 'Your free trial ends in :days day(s)',
        'greeting' => 'Hello :name,',
        'intro' => 'Your free trial ends in :days day(s), on :date.',
        'plan_label' => 'Plan',
        'paid_until_label' => 'Trial ends',
        'payment_note' => 'To keep your booking site and dashboard active after the trial, please choose a plan and arrange the payment by bank transfer or in person.',
        'outro' => 'If you have already paid, you can ignore this message — your payment will be recorded shortly.',
    ],

    // Service due reminder (operator, vehicle maintenance tracking)
    'service_due' => [
        'subject' => 'Service due soon — :vehicle',
        'greeting' => 'Hello,',
        'intro' => 'The vehicle :vehicle has a service due on :date.',
        'vehicle_label' => 'Vehicle',
        'due_on_label' => 'Due on',
        'last_service_label' => 'Last service',
        'outro' => 'Log a new service record once it is done to clear this reminder.',
    ],

    'review_request' => [
        'subject' => 'How was your rental with :operator?',
        'greeting' => 'Hello :name,',
        'intro' => 'Thank you for renting the :vehicle. We would love to hear how it went — it only takes a minute.',
        'button' => 'Leave a Review',
        'outro' => 'Thank you for choosing :operator.',
    ],
    'waitlist_slot_open' => [
        'subject' => 'Your dates just opened up at :operator',
        'greeting' => 'Hello :name,',
        'intro' => 'Good news — the :vehicle is now free from :start to :end, the dates you asked about.',
        'button' => 'Book these dates',
        'no_hold' => 'We have not reserved it for you — the vehicle goes to whoever books first, so do not wait too long.',
        'outro' => 'Thank you for choosing :operator.',
    ],
];
