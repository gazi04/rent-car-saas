<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover lets the concierge launcher pad itself past the
         home indicator on notched phones via env(safe-area-inset-bottom). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ ($title ?? null) ? $title . ' — ' . (tenant()?->name ?? config('app.name')) : (tenant()?->name ?? config('app.name')) }}</title>

    @php
        $colorPrimary   = tenant()?->colorPrimary()   ?? config('branding.defaults.color_primary',   '#2563eb');
        $colorSecondary = tenant()?->colorSecondary() ?? config('branding.defaults.color_secondary', '#1e40af');
        $fontFamily     = tenant()?->setting('font_family',     config('branding.defaults.font_family',     'Inter'));
        $fontAlias      = config('branding.fonts.' . $fontFamily . '.alias', 'inter');
    @endphp

    {{-- Self-hosted, and only the one face this tenant chose: @fonts() filters
         the build manifest by alias, so a Poppins tenant ships Poppins' two
         @font-face rules and one preload link — not all five families.
         $fontAlias comes from a curated config keyed by an allow-list-validated
         setting, so there is no injection path into this call. --}}
    @fonts([$fontAlias])

    {{-- Unlayered on purpose. Tailwind emits its @theme defaults inside
         `@layer theme`, and an unlayered declaration beats any layer regardless
         of source order — that is the whole mechanism by which a tenant's brand
         colour overrides the platform default. Never move this into a layer.
         tests/Feature/BrandingTest.php pins the literal `--color-primary: <hex>;`
         format of these two declarations. --}}
    <style>
        :root {
            --color-primary: {{ $colorPrimary }};
            --color-secondary: {{ $colorSecondary }};
            --font-family: '{{ $fontFamily }}', system-ui, sans-serif;
        }
        body { font-family: var(--font-family); }
        [x-cloak] { display: none !important; }
    </style>

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh overflow-x-clip bg-surface text-ink antialiased">

    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-surface-raised focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-ink focus:shadow-lg focus:ring-2 focus:ring-primary">
        {{ __('booking.skip_to_content') }}
    </a>

    {{-- Public header --}}
    <header class="sticky top-0 z-40 border-b border-line bg-surface-raised" x-data="{ open: false }">
        <x-ui.container>
            <div class="flex h-16 items-center justify-between gap-4">
                <a href="{{ route('public.home') }}" class="flex min-w-0 items-center gap-3 text-ink transition-opacity hover:opacity-80">
                    @if(tenant()?->logoUrl())
                        <img src="{{ tenant()->logoUrl() }}" alt="{{ tenant()->name }}" class="h-9 w-auto max-w-[11rem] object-contain sm:max-w-[14rem]">
                    @else
                        <span class="truncate text-lg font-semibold">{{ tenant()?->name ?? config('app.name') }}</span>
                    @endif
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden items-center gap-8 text-sm font-medium text-ink-muted md:flex">
                    <a href="{{ route('public.home') }}" class="transition-colors hover:text-ink">{{ __('booking.nav_home') }}</a>
                    <a href="{{ route('public.vehicles') }}" class="transition-colors hover:text-ink">{{ __('booking.nav_vehicles') }}</a>
                    <a href="{{ route('public.home') }}#about" class="transition-colors hover:text-ink">{{ __('booking.nav_about') }}</a>
                    <a href="#contact" class="transition-colors hover:text-ink">{{ __('booking.nav_contact') }}</a>
                </nav>

                <div class="flex shrink-0 items-center gap-1">
                    {{-- Language toggle --}}
                    <form method="POST" action="{{ route('public.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sq' ? 'en' : 'sq' }}">
                        <button type="submit"
                                class="inline-flex min-h-11 items-center rounded-control px-3 text-sm text-ink-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            {{ __('booking.language_toggle') }}
                        </button>
                    </form>

                    {{-- Mobile hamburger. 44px hit area: the icon stays 24px, the
                         button around it does the work. --}}
                    <button type="button"
                            class="inline-flex size-11 items-center justify-center rounded-control text-ink-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary md:hidden"
                            @click="open = !open"
                            :aria-expanded="open ? 'true' : 'false'"
                            aria-controls="public-mobile-nav"
                            aria-label="{{ __('booking.nav_menu') }}">
                        <flux:icon.bars-3 x-show="!open" class="size-6" />
                        <flux:icon.x-mark x-show="open" x-cloak class="size-6" />
                    </button>
                </div>
            </div>
        </x-ui.container>

        {{-- Mobile nav --}}
        <nav id="public-mobile-nav" x-show="open" x-cloak class="border-t border-line bg-surface-raised md:hidden">
            <x-ui.container class="space-y-1 py-3 text-sm font-medium text-ink-muted">
                <a href="{{ route('public.home') }}" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface-sunken hover:text-ink">{{ __('booking.nav_home') }}</a>
                <a href="{{ route('public.vehicles') }}" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface-sunken hover:text-ink">{{ __('booking.nav_vehicles') }}</a>
                <a href="{{ route('public.home') }}#about" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface-sunken hover:text-ink" @click="open = false">{{ __('booking.nav_about') }}</a>
                <a href="#contact" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface-sunken hover:text-ink" @click="open = false">{{ __('booking.nav_contact') }}</a>
            </x-ui.container>
        </nav>
    </header>

    {{-- No container and no padding here on purpose. Each page opens its own
         <x-ui.container>, which lets a full-bleed section be a plain <section>
         with a background instead of the old `left-1/2 w-screen -translate-x-1/2`
         trick — that used 100vw, which includes the scrollbar gutter and caused
         horizontal scroll. pb-24 keeps the fixed concierge launcher from
         covering the last control on the page. --}}
    <main id="main" class="pb-24">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer id="contact" class="scroll-mt-20 border-t border-line">
        <x-ui.container class="py-10">
            @php
                $footerText       = tenant()?->localizedSetting('footer_text');
                $socialFacebook   = tenant()?->socialFacebookUrl();
                $socialInstagram  = tenant()?->socialInstagramUrl();
                $contactPhone     = tenant()?->setting('contact_phone');
                $contactEmail     = tenant()?->setting('contact_email');
                $contactAddress   = tenant()?->setting('contact_address');
            @endphp

            <div class="flex flex-col gap-6 text-sm text-ink-muted sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="font-medium text-ink">{{ tenant()?->name ?? config('app.name') }}</p>
                    @if($footerText)
                        <p class="mt-1 max-w-xs">{{ $footerText }}</p>
                    @endif
                    @if($contactAddress)
                        <p class="mt-1">{{ $contactAddress }}</p>
                    @endif
                    @if($contactPhone)
                        <p class="mt-1">
                            <a href="tel:{{ $contactPhone }}" class="inline-flex min-h-11 items-center hover:text-ink">{{ $contactPhone }}</a>
                        </p>
                    @endif
                    @if($contactEmail)
                        <p>
                            <a href="mailto:{{ $contactEmail }}" class="inline-flex min-h-11 items-center hover:text-ink">{{ $contactEmail }}</a>
                        </p>
                    @endif
                </div>

                @if($socialFacebook || $socialInstagram)
                <div class="flex items-center gap-2">
                    @if($socialFacebook)
                        <a href="{{ $socialFacebook }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-control px-2 hover:text-ink" aria-label="Facebook">
                            Facebook
                        </a>
                    @endif
                    @if($socialInstagram)
                        <a href="{{ $socialInstagram }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-control px-2 hover:text-ink" aria-label="Instagram">
                            Instagram
                        </a>
                    @endif
                </div>
                @endif
            </div>

            <div class="mt-6 border-t border-line pt-4 text-center text-xs text-ink-faint">
                &copy; {{ date('Y') }} {{ tenant()?->name ?? config('app.name') }}. Powered by Renti.
            </div>
        </x-ui.container>
    </footer>

    <livewire:faq-concierge />

    @stack('scripts')
</body>
</html>
