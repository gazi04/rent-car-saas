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
];
