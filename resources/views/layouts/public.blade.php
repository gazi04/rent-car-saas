<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title . ' — ' . (tenant()?->name ?? config('app.name')) : (tenant()?->name ?? config('app.name')) }}</title>

    @php
        $colorPrimary   = tenant()?->setting('color_primary',   config('branding.defaults.color_primary',   '#2563eb'));
        $colorSecondary = tenant()?->setting('color_secondary', config('branding.defaults.color_secondary', '#1e40af'));
        $fontFamily     = tenant()?->setting('font_family',     config('branding.defaults.font_family',     'Inter'));
        $fontConfig     = config('branding.fonts.' . $fontFamily);
        $fontUrl        = $fontConfig['url'] ?? null;
    @endphp

    <style>
        :root {
            --color-primary: {{ $colorPrimary }};
            --color-secondary: {{ $colorSecondary }};
            --font-family: '{{ $fontFamily }}', system-ui, sans-serif;
        }
        body { font-family: var(--font-family); }
        [x-cloak] { display: none !important; }
    </style>

    @if($fontUrl)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ $fontUrl }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">

    {{-- Public header --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40" x-data="{ open: false }">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('public.home') }}" class="flex items-center gap-3 text-gray-900 hover:opacity-80 transition-opacity">
                    @if(tenant()?->logoUrl())
                        <img src="{{ tenant()->logoUrl() }}" alt="{{ tenant()->name }}" class="h-9 w-auto object-contain">
                    @else
                        <span class="text-lg font-semibold">{{ tenant()?->name ?? config('app.name') }}</span>
                    @endif
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                    <a href="{{ route('public.home') }}" class="hover:text-gray-900 transition-colors">{{ __('booking.nav_home') }}</a>
                    <a href="{{ route('public.vehicles') }}" class="hover:text-gray-900 transition-colors">{{ __('booking.nav_vehicles') }}</a>
                    <a href="{{ route('public.home') }}#about" class="hover:text-gray-900 transition-colors">{{ __('booking.nav_about') }}</a>
                    <a href="#contact" class="hover:text-gray-900 transition-colors">{{ __('booking.nav_contact') }}</a>
                </nav>

                <div class="flex items-center gap-4">
                    {{-- Language toggle --}}
                    <form method="POST" action="{{ route('public.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sq' ? 'en' : 'sq' }}">
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                            {{ __('booking.language_toggle') }}
                        </button>
                    </form>

                    {{-- Mobile hamburger --}}
                    <button type="button" class="md:hidden text-gray-600 hover:text-gray-900" @click="open = !open" aria-label="Menu">
                        <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                        <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile nav --}}
        <nav x-show="open" x-cloak class="md:hidden border-t border-gray-100 bg-white">
            <div class="px-4 py-3 space-y-1 text-sm font-medium text-gray-600">
                <a href="{{ route('public.home') }}" class="block px-2 py-2 rounded hover:bg-gray-50 hover:text-gray-900">{{ __('booking.nav_home') }}</a>
                <a href="{{ route('public.vehicles') }}" class="block px-2 py-2 rounded hover:bg-gray-50 hover:text-gray-900">{{ __('booking.nav_vehicles') }}</a>
                <a href="{{ route('public.home') }}#about" class="block px-2 py-2 rounded hover:bg-gray-50 hover:text-gray-900" @click="open = false">{{ __('booking.nav_about') }}</a>
                <a href="#contact" class="block px-2 py-2 rounded hover:bg-gray-50 hover:text-gray-900" @click="open = false">{{ __('booking.nav_contact') }}</a>
            </div>
        </nav>
    </header>

    {{-- Main content --}}
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer id="contact" class="border-t border-gray-200 mt-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @php
                $footerText       = tenant()?->setting('footer_text');
                $socialFacebook   = tenant()?->setting('social_facebook');
                $socialInstagram  = tenant()?->setting('social_instagram');
                $contactPhone     = tenant()?->setting('contact_phone');
                $contactEmail     = tenant()?->setting('contact_email');
                $contactAddress   = tenant()?->setting('contact_address');
            @endphp

            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 text-sm text-gray-500">
                <div>
                    <p class="font-medium text-gray-700">{{ tenant()?->name ?? config('app.name') }}</p>
                    @if($footerText)
                        <p class="mt-1 max-w-xs">{{ $footerText }}</p>
                    @endif
                    @if($contactAddress)
                        <p class="mt-1">{{ $contactAddress }}</p>
                    @endif
                    @if($contactPhone)
                        <p class="mt-1">
                            <a href="tel:{{ $contactPhone }}" class="hover:text-gray-900">{{ $contactPhone }}</a>
                        </p>
                    @endif
                    @if($contactEmail)
                        <p class="mt-1">
                            <a href="mailto:{{ $contactEmail }}" class="hover:text-gray-900">{{ $contactEmail }}</a>
                        </p>
                    @endif
                </div>

                @if($socialFacebook || $socialInstagram)
                <div class="flex items-center gap-4">
                    @if($socialFacebook)
                        <a href="{{ $socialFacebook }}" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900" aria-label="Facebook">
                            Facebook
                        </a>
                    @endif
                    @if($socialInstagram)
                        <a href="{{ $socialInstagram }}" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900" aria-label="Instagram">
                            Instagram
                        </a>
                    @endif
                </div>
                @endif
            </div>

            <div class="mt-6 border-t border-gray-100 pt-4 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} {{ tenant()?->name ?? config('app.name') }}. Powered by RentACar SaaS.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
