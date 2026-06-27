<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title . ' — ' . (tenant()?->name ?? config('app.name')) : (tenant()?->name ?? config('app.name')) }}</title>

    <style>
        :root {
            --color-primary: #2563eb;
            --color-primary-hover: #1d4ed8;
            --color-secondary: #1e40af;
            --font-family: 'Instrument Sans', system-ui, sans-serif;
        }
        body { font-family: var(--font-family); }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">

    {{-- Public header --}}
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('public.home') }}" class="text-lg font-semibold text-gray-900 hover:text-blue-600 transition-colors">
                    {{ tenant()?->name ?? config('app.name') }}
                </a>

                {{-- Language toggle --}}
                <form method="POST" action="{{ route('public.language') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sq' ? 'en' : 'sq' }}">
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                        {{ __('booking.language_toggle') }}
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Main content --}}
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="border-t border-gray-200 mt-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} {{ tenant()?->name ?? config('app.name') }}. Powered by RentACar SaaS.
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
