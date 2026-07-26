<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('booking.confirm_cancel_heading') }} — {{ tenant()?->name ?? config('app.name') }}</title>
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
                <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900 mb-2">{{ __('booking.confirm_cancel_heading') }}</h1>
                <p class="text-sm text-gray-600 mb-1">{{ __('booking.confirm_cancel_body') }}</p>
                <p class="text-xs text-gray-400 mb-6">{{ __('booking.booking_reference') }}: {{ $booking->reference }}</p>

                <form method="POST" action="{{ $cancelUrl }}" class="flex flex-col items-center gap-3">
                    @csrf
                    <button type="submit"
                            class="rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                        {{ __('booking.confirm_cancel_button') }}
                    </button>
                    <a href="{{ route('public.home') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        {{ __('booking.keep_booking') }}
                    </a>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
