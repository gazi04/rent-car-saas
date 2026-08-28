<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name') }} — {{ __('marketing.hero_heading') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts(['instrument-sans'])
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-surface-raised text-ink antialiased">

    {{-- Header. The nav links used to be `hidden sm:flex` with no replacement,
         so on a phone Features / How it works / Pricing were unreachable from
         the header entirely — the only route to them was the footer. --}}
    <header class="sticky top-0 z-40 border-b border-line bg-surface-raised/80 backdrop-blur">
        <x-ui.container>
            <div class="flex h-16 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 text-lg font-bold text-ink">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-primary text-on-primary">
                        <flux:icon.truck class="size-5" />
                    </span>
                    <span class="truncate">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-ink-muted sm:flex">
                    <a href="#features" class="transition-colors hover:text-ink">{{ __('marketing.nav_features') }}</a>
                    <a href="#how-it-works" class="transition-colors hover:text-ink">{{ __('marketing.nav_how_it_works') }}</a>
                    <a href="#pricing" class="transition-colors hover:text-ink">{{ __('marketing.nav_pricing') }}</a>
                </nav>

                <div class="flex shrink-0 items-center gap-1">
                    <form method="POST" action="{{ route('marketing.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sq' ? 'en' : 'sq' }}">
                        <button type="submit"
                                class="inline-flex min-h-11 items-center rounded-control px-2 text-sm text-ink-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            {{ __('marketing.language_toggle') }}
                        </button>
                    </form>

                    <x-ui.button :href="route('operator.register')" size="sm" class="hidden sm:inline-flex">
                        {{ __('marketing.nav_start_trial') }}
                    </x-ui.button>

                </div>
            </div>
        </x-ui.container>

        {{-- Mobile nav as a native <details>: no JavaScript at all. This page
             loads no Livewire, so it has no Alpine either, and a nav toggle is
             not worth a bundle or a third-party script. --}}
        <details class="group sm:hidden">
            <summary class="flex cursor-pointer list-none items-center justify-center gap-2 border-t border-line py-3 text-sm font-medium text-ink-muted marker:content-none hover:text-ink [&::-webkit-details-marker]:hidden">
                <flux:icon.bars-3 class="size-5 group-open:hidden" />
                <flux:icon.x-mark class="hidden size-5 group-open:block" />
                {{ __('marketing.nav_menu') }}
            </summary>

            <x-ui.container class="space-y-1 border-t border-line py-3 text-sm font-medium text-ink-muted">
                <a href="#features" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface hover:text-ink">{{ __('marketing.nav_features') }}</a>
                <a href="#how-it-works" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface hover:text-ink">{{ __('marketing.nav_how_it_works') }}</a>
                <a href="#pricing" class="flex min-h-11 items-center rounded-control px-2 hover:bg-surface hover:text-ink">{{ __('marketing.nav_pricing') }}</a>
                <x-ui.button :href="route('operator.register')" class="mt-2 w-full">
                    {{ __('marketing.nav_start_trial') }}
                </x-ui.button>
            </x-ui.container>
        </details>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-primary/5 via-surface-raised to-surface-raised">
        <div class="pointer-events-none absolute inset-x-0 -top-40 h-96 bg-[radial-gradient(60%_60%_at_50%_0%,rgba(37,99,235,0.12),transparent)]"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-28 pb-16 text-center">
            <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-secondary">
                {{ __('marketing.hero_badge') }}
            </span>

            <h1 class="mt-6 text-4xl sm:text-6xl font-bold tracking-tight text-ink max-w-3xl mx-auto">
                {{ __('marketing.hero_heading') }}
            </h1>
            <p class="mt-6 text-lg text-ink-muted max-w-2xl mx-auto">
                {{ __('marketing.hero_subheading') }}
            </p>

            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('operator.register') }}"
                   class="w-full sm:w-auto rounded-control bg-primary px-6 py-3 text-base font-semibold text-on-primary shadow-lg shadow-primary/20 hover:bg-secondary transition-colors">
                    {{ __('marketing.hero_cta_primary') }}
                </a>
                <a href="#pricing"
                   class="w-full sm:w-auto rounded-control border border-line-strong bg-surface-raised px-6 py-3 text-base font-semibold text-ink-muted hover:bg-surface transition-colors">
                    {{ __('marketing.hero_cta_secondary') }}
                </a>
            </div>
            <p class="mt-4 text-sm text-ink-muted">{{ __('marketing.hero_note') }}</p>

            {{-- Browser mockup --}}
            <div class="mt-16 mx-auto max-w-4xl rounded-panel border border-line bg-surface-raised shadow-2xl shadow-ink/10 overflow-hidden text-left">
                <div class="flex items-center gap-2 border-b border-line bg-surface px-4 py-3">
                    <span class="size-3 rounded-full bg-critical/40"></span>
                    <span class="size-3 rounded-full bg-notice/40"></span>
                    <span class="size-3 rounded-full bg-positive/40"></span>
                    <span class="ml-4 flex-1 max-w-sm rounded-control bg-surface-raised border border-line px-3 py-1 text-xs text-ink-faint">
                        {{ __('marketing.hero_mock_url') }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 sm:gap-4 sm:p-6">
                    <div class="col-span-2 h-20 rounded-control bg-gradient-to-r from-primary to-secondary sm:col-span-3"></div>
                    @for ($i = 0; $i < 6; $i++)
                        <div class="rounded-control border border-line p-3">
                            <div class="aspect-video rounded bg-surface-sunken"></div>
                            <div class="mt-2 h-2.5 w-3/4 rounded bg-line"></div>
                            <div class="mt-1.5 h-2.5 w-1/2 rounded bg-surface-sunken"></div>
                            <div class="mt-3 h-6 w-20 rounded bg-primary/15"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="border-y border-line bg-surface">
        <div class="max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 grid sm:grid-cols-3 gap-8 text-center">
            <div>
                <p class="text-2xl font-bold text-ink">{{ __('marketing.stat_setup_value') }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('marketing.stat_setup_label') }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-ink">{{ __('marketing.stat_languages_value') }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('marketing.stat_languages_label') }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-ink">{{ __('marketing.stat_commission_value') }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('marketing.stat_commission_label') }}</p>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-ink">{{ __('marketing.features_heading') }}</h2>
            <p class="mt-4 text-ink-muted">{{ __('marketing.features_subheading') }}</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-8">
            @foreach ([
                ['key' => 'storefront', 'icon' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418'],
                ['key' => 'fleet', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                ['key' => 'automation', 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
                ['key' => 'dashboard', 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
            ] as $feature)
                <div class="rounded-panel border border-line p-8 hover:border-primary/20 hover:shadow-md transition-all">
                    <span class="flex h-11 w-11 items-center justify-center rounded-control bg-primary/10 text-primary mb-5">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $feature['icon'] }}"/>
                        </svg>
                    </span>
                    <h3 class="text-lg font-semibold text-ink mb-2">{{ __('marketing.feature_' . $feature['key'] . '_title') }}</h3>
                    <p class="text-sm text-ink-muted leading-relaxed">{{ __('marketing.feature_' . $feature['key'] . '_body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="bg-surface border-y border-line">
        <div class="max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-ink">{{ __('marketing.how_heading') }}</h2>
                <p class="mt-4 text-ink-muted">{{ __('marketing.how_subheading') }}</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-8">
                @foreach ([1, 2, 3] as $step)
                    <div class="relative bg-surface-raised rounded-panel border border-line p-8">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-on-primary font-bold mb-5">
                            {{ $step }}
                        </span>
                        <h3 class="text-lg font-semibold text-ink mb-2">{{ __('marketing.how_step_' . $step . '_title') }}</h3>
                        <p class="text-sm text-ink-muted leading-relaxed">{{ __('marketing.how_step_' . $step . '_body') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-ink">{{ __('marketing.pricing_heading') }}</h2>
            <p class="mt-4 text-ink-muted">{{ __('marketing.pricing_subheading') }}</p>
        </div>

        {{-- One card template over the live plans. These were four hand-written
             blocks with the monthly prices typed straight into the markup,
             while the real prices live in the `plans` table and are editable
             from the admin panel — so an admin changing a price left this page
             advertising the old one. $plans comes from the view composer in
             AppServiceProvider. --}}
        <div class="grid items-start gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($plans as $plan)
                @php
                    $isFeatured = $plan->slug === 'standard';
                    $langKey = 'marketing.plan_'.$plan->slug;
                @endphp

                <div @class([
                    'relative flex h-full flex-col rounded-panel p-6',
                    'border-2 border-primary shadow-lg shadow-primary/10' => $isFeatured,
                    'border border-line' => ! $isFeatured,
                ]) wire:key="plan-{{ $plan->slug }}">
                    @if ($isFeatured)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-on-primary">
                            {{ __('marketing.plan_standard_badge') }}
                        </span>
                    @endif

                    <h3 class="text-base font-semibold text-ink">{{ __($langKey.'_name') }}</h3>

                    <p class="mt-4">
                        @if ((float) $plan->price <= 0)
                            <span class="text-3xl font-bold text-ink">{{ __('marketing.plan_trial_price') }}</span>
                        @else
                            <span class="text-3xl font-bold text-ink">{{ __('booking.currency_symbol') }}{{ number_format((float) $plan->price, 0) }}</span>
                        @endif
                        <span class="text-sm text-ink-muted">{{ __($langKey.'_period') }}</span>
                    </p>

                    <p class="mt-3 text-sm text-ink-muted">{{ __($langKey.'_tagline') }}</p>

                    <ul class="mt-6 flex-1 space-y-2 text-sm text-ink-muted">
                        @foreach (['storefront', 'dashboard', 'agreements', 'bilingual'] as $feature)
                            <li class="flex items-start gap-2">
                                <flux:icon.check class="mt-0.5 size-4 shrink-0 text-positive" />
                                {{ __('marketing.plan_feature_'.$feature) }}
                            </li>
                        @endforeach

                        @if ($plan->slug === 'pro')
                            <li class="flex items-start gap-2">
                                <flux:icon.check class="mt-0.5 size-4 shrink-0 text-positive" />
                                {{ __('marketing.plan_feature_support_priority') }}
                            </li>
                        @elseif ((float) $plan->price > 0)
                            <li class="flex items-start gap-2">
                                <flux:icon.check class="mt-0.5 size-4 shrink-0 text-positive" />
                                {{ __('marketing.plan_feature_support_email') }}
                            </li>
                        @endif
                    </ul>

                    <x-ui.button :href="route('operator.register')"
                                 :variant="$isFeatured ? 'primary' : 'secondary'"
                                 class="mt-6 w-full">
                        {{ __('marketing.plan_cta') }}
                    </x-ui.button>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA band --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-secondary to-primary">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_100%_at_50%_0%,rgba(255,255,255,0.12),transparent)]"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h2 class="text-3xl font-bold text-on-primary">{{ __('marketing.cta_heading') }}</h2>
            <p class="mt-3 text-on-primary/80">{{ __('marketing.cta_subheading') }}</p>
            <a href="{{ route('operator.register') }}"
               class="mt-8 inline-block rounded-control bg-surface-raised px-6 py-3 text-base font-semibold text-secondary hover:bg-primary/10 transition-colors">
                {{ __('marketing.cta_button') }}
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-line bg-surface">
        <div class="max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid sm:grid-cols-3 gap-10 text-sm">
                <div>
                    <p class="font-semibold text-ink">{{ config('app.name') }}</p>
                    <p class="mt-2 text-ink-muted max-w-xs">{{ __('marketing.footer_tagline') }}</p>
                </div>
                <div>
                    <p class="font-semibold text-ink">{{ __('marketing.footer_product') }}</p>
                    <ul class="mt-2 space-y-2 text-ink-muted">
                        <li><a href="#features" class="hover:text-ink">{{ __('marketing.nav_features') }}</a></li>
                        <li><a href="#how-it-works" class="hover:text-ink">{{ __('marketing.nav_how_it_works') }}</a></li>
                        <li><a href="#pricing" class="hover:text-ink">{{ __('marketing.nav_pricing') }}</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-ink">{{ __('marketing.footer_get_started') }}</p>
                    <ul class="mt-2 space-y-2 text-ink-muted">
                        <li><a href="{{ route('operator.register') }}" class="hover:text-ink">{{ __('marketing.nav_start_trial') }}</a></li>
                    </ul>
                </div>
            </div>

            <p class="mt-10 border-t border-line pt-6 text-sm text-ink-faint">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>
        </div>
    </footer>
</body>
</html>
