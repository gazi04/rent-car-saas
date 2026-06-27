<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('booking.booking_cancelled') }} — {{ tenant()?->name ?? config('app.name') }}</title>
    <style>:root { --font-family: 'Instrument Sans', system-ui, sans-serif; } body { font-family: var(--font-family); }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center">
            <a href="{{ route('public.home') }}" class="text-lg font-semibold text-gray-900 hover:text-blue-600 transition-colors">
                {{ tenant()?->name ?? config('app.name') }}
            </a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                @if ($alreadyDone)
                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900 mb-2">{{ __('booking.booking_already_cancelled') }}</h1>
                @else
                    <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900 mb-2">{{ __('booking.booking_cancelled') }}</h1>
                    <p class="text-sm text-gray-600 mb-4">{{ __('booking.cancellation_confirmed') }}</p>
                @endif

                <p class="text-xs text-gray-400 mb-6">{{ __('booking.booking_reference') }}: {{ $booking->reference }}</p>

                <a href="{{ route('public.home') }}"
                   class="rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
                    {{ __('booking.back_to_fleet') }}
                </a>
            </div>
        </div>
    </main>
</body>
</html>
