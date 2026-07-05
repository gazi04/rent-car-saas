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

    // Kujtesa e rinovimit të abonimit (operatori, faturim manual B2B)
    'subscription_renewal_reminder' => [
        'subject' => 'Abonimi juaj rinovohet pas :days dite(sh)',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Periudha e abonimit tuaj përfundon pas :days dite(sh), më :date.',
        'plan_label' => 'Plani',
        'paid_until_label' => 'Paguar deri më',
        'payment_note' => 'Për ta mbajtur aktive faqen tuaj të rezervimeve dhe panelin, ju lutemi kryeni pagesën me transfertë bankare ose personalisht para përfundimit të periudhës.',
        'outro' => 'Nëse tashmë keni paguar, mund ta injoroni këtë mesazh — pagesa juaj do të regjistrohet së shpejti.',
    ],

    // Kujtesa e përfundimit të provës falas (operatori, periudha e parë falas)
    'trial_expiring_reminder' => [
        'subject' => 'Prova juaj falas përfundon pas :days dite(sh)',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Prova juaj falas përfundon pas :days dite(sh), më :date.',
        'plan_label' => 'Plani',
        'paid_until_label' => 'Prova përfundon',
        'payment_note' => 'Për ta mbajtur aktive faqen tuaj të rezervimeve dhe panelin pas provës, ju lutemi zgjidhni një plan dhe kryeni pagesën me transfertë bankare ose personalisht.',
        'outro' => 'Nëse tashmë keni paguar, mund ta injoroni këtë mesazh — pagesa juaj do të regjistrohet së shpejti.',
    ],

    // Kujtesa e servisit (operatori, gjurmimi i mirëmbajtjes së automjeteve)
    'service_due' => [
        'subject' => 'Servisi afër afatit — :vehicle',
        'greeting' => 'Përshëndetje,',
        'intro' => 'Automjeti :vehicle ka një servis të planifikuar më :date.',
        'vehicle_label' => 'Automjeti',
        'due_on_label' => 'Afati',
        'last_service_label' => 'Servisi i fundit',
        'outro' => 'Regjistroni një servis të ri sapo të kryhet për ta pastruar këtë kujtesë.',
    ],

    // Kërkesa për vlerësim
    'review_request' => [
        'subject' => 'Si ishte qiraja juaj me :operator?',
        'greeting' => 'Përshëndetje :name,',
        'intro' => 'Faleminderit që morët me qira :vehicle. Do të donim të dëgjonim përvojën tuaj — merr vetëm një minutë.',
        'button' => 'Lini një vlerësim',
        'outro' => 'Faleminderit që zgjodhët :operator.',
    ],
];
