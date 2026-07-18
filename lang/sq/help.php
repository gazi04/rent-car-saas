<?php

return [
    'tooltip' => 'Keni nevojë për ndihmë?',
    'fallback_title' => 'Ndihmë',
    'fallback_body' => 'Ndihma për këtë faqe së shpejti.',

    'templates' => [
        'title' => 'Shabllone të Personalizuara',
        'body' => [
            'Rishkruani kushtet e kontratës së qirasë dhe tekstin e email-eve të rezervimit që u dërgohen klientëve, në secilën gjuhë (Shqip/Anglisht).',
            'Lëreni një fushë bosh që të përdoret teksti i paracaktuar për atë fushë.',
            'Përdorni vendmbajtëset (placeholders) më poshtë brenda çdo fushe — ato zëvendësohen me të dhënat reale të rezervimit kur gjenerohet kontrata/email-i.',
        ],
    ],

    'branding' => [
        'title' => 'Personalizimi i Markës',
        'body' => [
            'Personalizoni pamjen e faqes suaj publike të rezervimeve: logo, ngjyrat e markës, fonti dhe struktura e faqeve kryesore/automjeteve/detajeve të automjetit.',
            'Skeda e Përmbajtjes: titulli kryesor, teksti "rreth nesh" dhe përshkrimet e shërbimeve — shkruar në secilën gjuhë (Shqip/Anglisht), shfaqur në faqen tuaj publike në atë gjuhë.',
            'Skeda e Kontaktit & Footer-it: telefoni, email-i, adresa, teksti i footer-it dhe lidhjet e rrjeteve sociale të shfaqura në të gjithë faqen tuaj publike.',
            'Ndryshimet aplikohen menjëherë pas ruajtjes — nuk nevojitet ripublikim.',
        ],
    ],

    'reports' => [
        'title' => 'Raportet',
        'body' => [
            'Zgjidhni një datë fillimi dhe mbarimi për të parë numrin e rezervimeve, të ardhurat dhe shfrytëzimin për automjet në atë periudhë.',
            'Të ardhurat llogarisin vetëm rezervimet aktive dhe të përfunduara — ato në pritje, të refuzuara dhe të anuluara nuk përfshihen.',
            'Shfrytëzimi është ditët e rezervuara ÷ ditët në periudhë, për secilin automjet — përqindjet e ulëta tregojnë makina joaktive që ia vlen të promovohen ose t\'u ndryshohet çmimi.',
            'Eksporto CSV shkarkon çdo rezervim që përputhet me periudhën e zgjedhur, përfshirë referencën, klientin, automjetin, datat, statusin dhe totalin.',
            'Harta e shfrytëzimit tregon cili automjet ishte i zënë në cilën ditë. Ngjyrat e rezervimeve përputhen me kalendarin e disponueshmërisë, ndërsa vijat e pjerrëta gri shënojnë datat që i keni bllokuar manualisht. Rreshti i fundit është kërkesa e flotës — sa nga flota juaj ishte jashtë atë ditë.',
            'Harta dhe tabela e shfrytëzimit më lart u përgjigjen pyetjeve të ndryshme, prandaj shifrat e tyre ndryshojnë qëllimisht. Harta numëron ditët kalendarike që preken (një makinë e marrë të hënën në 09:00 dhe e kthyer të mërkurën në 09:00 prek 3 ditë), ndërsa tabela numëron kohëzgjatjen e qirasë (48 orë = 2 ditë). Harta përfshin gjithashtu rezervimet e përfunduara që muajt e kaluar të mos dalin bosh, ndërsa tabela numëron vetëm ato në pritje, të konfirmuara dhe aktive.',
        ],
    ],

    'promo_codes' => [
        'title' => 'Kodet Promocionale',
        'body' => [
            'Krijoni kode zbritjeje që klientët mund t\'i shkruajnë gjatë rezervimit — një shumë fikse ose një përqindje zbritje nga totali.',
            'Vendosni një datë skadimi dhe/ose një limit përdorimi për të kontrolluar sa gjatë dhe sa herë mund të përdoret një kod; lëreni bosh njërën për pa limit.',
            'Vetëm një kod promocional mund të aplikohet për rezervim — kodet nuk kombinohen me njëri-tjetrin.',
            'Çaktivizimi i një kodi ndalon përdorimet e reja menjëherë pa fshirë historikun e tij.',
        ],
    ],

    'service_records' => [
        'title' => 'Regjistrat e Shërbimit & Mirëmbajtjes',
        'body' => [
            'Regjistroni historikun e mirëmbajtjes dhe shërbimit për secilin automjet — falas në çdo plan, pa kujtesa.',
            'Në planet me kujtesa mirëmbajtjeje, një shërbim i afërt/i vonuar mund të bllokojë automatikisht kalendarin e automjetit derisa të shënohet i shërbyer, duke parandaluar fillimin e një rezervimi në një automjet të pasigurt.',
            'Shënimi i një regjistri si i përfunduar heq çdo bllokim automatik që kishte krijuar për atë shërbim.',
        ],
    ],

    'vehicles' => [
        'title' => 'Flota & Automjetet',
        'body' => [
            'Vendosni çmime orare, ditore, javore dhe mujore për secilin automjet — motori i rezervimeve zgjedh çmimin më të lirë të aplikueshëm për kohëzgjatjen e kërkuar.',
            'Fushat e personalizuara ju lejojnë të regjistroni detaje shtesë të automjetit (p.sh. kilometrazhi, transmisioni) që shfaqen në listimin publik pa nevojën për ndryshim kodi.',
            'Fotot konvertohen automatikisht në WebP dhe fotoja e parë e ngarkuar përdoret si imazh kryesor në faqen publike.',
            'Plani juaj kufizon numrin e automjeteve që mund të listoni — butoni "Shto automjet" çaktivizohet pasi arrini atë limit.',
        ],
    ],

    'staff' => [
        'title' => 'Llogaritë e Stafit',
        'body' => [
            'Shtoni llogari stafi për recepsionin që punonjësit të menaxhojnë rezervimet dhe flotën pa ndarë login-in tuaj si pronar.',
            'Llogaritë e stafit nuk mund të qasen në faturim, personalizimin e markës apo cilësimet e planit — ato mbeten vetëm për pronarin.',
            'Plani juaj kufizon numrin e vendeve për staf — butoni "Shto staf" çaktivizohet pasi arrini atë limit.',
        ],
    ],

    'bookings' => [
        'title' => 'Rezervimet',
        'body' => [
            'Rezervimet kalojnë nëpër një cikël: Në pritje → Konfirmuar → Aktive → Përfunduar, ose Refuzuar/Anuluar në çdo moment para se të bëhet Aktive.',
            'Përdorni "Konfirmo" pasi të keni verifikuar disponueshmërinë dhe marrëveshjet e pagesës me klientin, dhe "Refuzo" nëse nuk mund ta përmbushni kërkesën.',
            'Shënojeni rezervimin "Aktiv" në dorëzim dhe "Përfunduar" në kthim — kjo është ajo që përcakton të ardhurat dhe shfrytëzimin në Raporte.',
            'Këtu mund të krijoni edhe një rezervim manualisht për klientë me telefon/pa takim, pa kaluar nëpër faqen publike.',
        ],
    ],

    'availability_calendar' => [
        'title' => 'Kalendari i Disponueshmërisë',
        'body' => [
            'Tregon çdo rezervim dhe periudhë të bllokuar manualisht, të ngjyrosura sipas statusit — klikoni një rezervim për ta hapur.',
            'Përdorni "Blloko datat" (ose zgjidhni një periudhë duke tërhequr) për ta hequr përkohësisht një automjet nga faqja publike, p.sh. për mirëmbajtje ose përdorim personal.',
            'Datat e bllokuara parandalojnë rezervimet e reja publike që përputhen me to, njësoj si një rezervim ekzistues.',
            'Filtroni sipas automjetit duke përdorur menynë rënëse për të parë orarin e një makine në një kohë.',
        ],
    ],

    'needs_attention' => [
        'title' => 'Kërkon Vëmendje',
        'body' => [
            'Listoni rezervimet që kërkojnë një vendim tuajin tani: ato ende Në pritje konfirmimi, dhe qiratë Aktive datat e kthimit të të cilave kanë kaluar tashmë (të vonuara).',
            'Klikoni çdo rresht për ta hapur rezervimin dhe për të vepruar — konfirmo, refuzo, ose shëno të kthyer.',
            'Kjo listë pastrohet vetë ndërsa rezervimet kalojnë Në pritje/vonesën — nuk nevojitet largim manual.',
        ],
    ],

    'business_summary' => [
        'title' => 'Përmbledhja e Biznesit me AI',
        'body' => [
            'Gjeneron një përmbledhje të shkurtër me shkrim të rezervimeve dhe të ardhurave tuaja të fundit duke përdorur AI, që ju jep një pasqyrë të shpejtë se si po shkon biznesi pa u dashur të kërkoni nëpër Raporte.',
            '"Gjenero tani" e ri-gjeneron përmbledhjen sipas kërkesës për periudhën e konfiguruar — çdo gjenerim zëvendëson atë të mëparshmin të shfaqur këtu.',
            'Kjo veçori është e disponueshme vetëm në planet që e përfshijnë.',
        ],
    ],

    'waitlist' => [
        'title' => 'Lista e pritjes',
        'body' => [
            'Personat që kërkuan një nga automjetet tuaja në data që ishin tashmë të zëna, sipas radhës së regjistrimit. Kalendari juaj i rezervimeve i fsheh datat e zëna, prandaj pa këtë listë nuk do ta merrnit vesh kurrë këtë kërkesë.',
            'Kur një rezervim anulohet ose refuzohet, personi i parë në radhë për ato data njoftohet automatikisht me email. Personat që presin për data që nuk mbivendosen njoftohen të gjithë, sepse nuk konkurrojnë për të njëjtën periudhë.',
            'Njoftimi nuk e rezervon automjetin — e merr kush e rezervon i pari. Nëse personi i parë nuk rezervon, datat i ofrohen të nesërmit personit tjetër në radhë.',
            'Regjistrimet vijnë vetëm nga faqja juaj publike; nuk mund t\'i krijoni këtu. Fshini ato që nuk doni t\'i mbani.',
        ],
    ],
];
