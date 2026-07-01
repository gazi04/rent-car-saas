<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }} — {{ config('app.name') }}</title>
    <style>:root { --font-family: 'Instrument Sans', system-ui, sans-serif; } body { font-family: var(--font-family); }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50">
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="max-w-lg mx-auto text-center">
            @php
                $message = match ($status) {
                    'pending' => __('This booking site is not live yet.'),
                    'suspended' => __('This booking site is temporarily unavailable. Please contact the business directly.'),
                    'cancelled' => __('This booking site is no longer available.'),
                    default => __('This booking site is currently unavailable.'),
                };
            @endphp

            <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ $name }}</h1>
            <p class="text-gray-600">{{ $message }}</p>
        </div>
    </main>
</body>
</html>
