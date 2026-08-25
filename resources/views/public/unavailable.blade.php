{{-- Deliberately NOT the storefront layout.

     This renders for pending, suspended and cancelled tenants. Giving it the
     public chrome would show nav links that immediately fail and a concierge
     widget for a business that is not trading — dishonest, and
     tests/Feature/TenantStatusGateTest.php asserts this page carries no
     "Powered by RentACar SaaS" footer. It stays a plain standalone document. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }} — {{ config('app.name') }}</title>

    {{-- The inline rule below names Instrument Sans but nothing was loading it,
         so this always fell back to system-ui. --}}
    @fonts(['instrument-sans'])

    <style>:root { --font-family: 'Instrument Sans', system-ui, sans-serif; } body { font-family: var(--font-family); }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface text-ink antialiased">
    <main class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-lg text-center">
            @php
                $message = match ($status) {
                    'pending' => __('This booking site is not live yet.'),
                    'suspended' => __('This booking site is temporarily unavailable. Please contact the business directly.'),
                    'cancelled' => __('This booking site is no longer available.'),
                    default => __('This booking site is currently unavailable.'),
                };
            @endphp

            <h1 class="mb-3 text-2xl font-bold text-ink">{{ $name }}</h1>
            <p class="text-ink-muted">{{ $message }}</p>
        </div>
    </main>
</body>
</html>
