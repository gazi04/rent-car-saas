<?php

return [
    // Kërkesë rezervimi e marrë (klienti)
    'booking_received' => [
        'subject' => 'Kërkesë Rezervimi e Marrë — :reference',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Kemi marrë kërkesën tuaj për rezervim. Operatori do ta shqyrtojë dhe konfirmojë së shpejti.',
        'reference_label' => 'Referenca',
        'vehicle_label' => 'Automjeti',
        'dates_label' => 'Datat',
        'total_label' => 'Totali',
        'cancel_action' => 'Anulo Rezervimin',
        'cancel_note' => 'Mund ta anuloni këtë rezervim brenda 24 orëve duke përdorur lidhjen e mësipërme.',
        'outro' => 'Faleminderit që zgjodhët :operator.',
    ],

    // Njoftim rezervimi i ri (operatori)
    'new_booking_alert' => [
        'subject' => 'Kërkesë e Re Rezervimi — :reference',
        'bell_title' => 'Kërkesë e Re Rezervimi',
        'greeting' => 'Përshëndetje,',
        'intro' => 'Është paraqitur një kërkesë e re rezervimi.',
        'customer_label' => 'Klienti',
        'vehicle_label' => 'Automjeti',
        'dates_label' => 'Datat',
        'total_label' => 'Totali',
        'view_action' => 'Shiko Rezervimin',
        'outro' => 'Hyni në panelin tuaj për të konfirmuar ose refuzuar këtë rezervim.',
    ],

    // Rezervimi konfirmuar (klienti)
    'booking_confirmed' => [
        'subject' => 'Rezervimi Konfirmuar — :reference',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Lajme të mira — rezervimi juaj është konfirmuar!',
        'reference_label' => 'Referenca',
        'vehicle_label' => 'Automjeti',
        'dates_label' => 'Datat',
        'total_label' => 'Totali',
        'payment_note' => 'Pagesa bëhet gjatë marrjes — ju lutemi rregullojeni me operatorin.',
        'agreement_button' => 'Shkarko Kontratën',
        'outro' => 'Faleminderit që rezervuat me :operator.',
    ],

    // Rezervimi refuzuar (klienti)
    'booking_rejected' => [
        'subject' => 'Informacion mbi Rezervimin — :reference',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Fatkeqësisht, operatori nuk mundi të konfirmojë kërkesën tuaj për rezervim.',
        'reference_label' => 'Referenca',
        'vehicle_label' => 'Automjeti',
        'dates_label' => 'Datat',
        'outro' => 'Ju lutemi provoni data të tjera ose kontaktoni drejtpërdrejt :operator.',
    ],

    // Rezervimi anuluar (klienti ose operatori)
    'booking_cancelled' => [
        'subject' => 'Rezervimi Anuluar — :reference',
        'bell_title' => 'Rezervimi Anuluar nga Klienti',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Rezervimi juaj është anuluar.',
        'reference_label' => 'Referenca',
        'vehicle_label' => 'Automjeti',
        'dates_label' => 'Datat',
        'outro' => 'Nëse nuk e keni kërkuar këtë anulim, ju lutemi kontaktoni :operator.',
    ],
];
