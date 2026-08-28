<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Narrowed deliberately: a bare @fonts emits @font-face rules AND preload
     links for every family in vite.config.js. The app shell only ever renders
     in Instrument Sans, so preloading the five tenant-selectable storefront
     faces here would be ~15 wasted font fetches on every authenticated page. --}}
@fonts(['instrument-sans'])

@vite(['resources/css/app.css'])
@fluxAppearance
