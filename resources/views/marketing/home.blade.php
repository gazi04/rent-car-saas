<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — {{ __('marketing.hero_heading') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-gray-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        </svg>
                    </span>
                    {{ config('app.name') }}
                </a>

                <nav class="hidden sm:flex items-center gap-8 text-sm font-medium text-gray-600">
                    <a href="#features" class="hover:text-gray-900 transition-colors">{{ __('marketing.nav_features') }}</a>
                    <a href="#how-it-works" class="hover:text-gray-900 transition-colors">{{ __('marketing.nav_how_it_works') }}</a>
                    <a href="#pricing" class="hover:text-gray-900 transition-colors">{{ __('marketing.nav_pricing') }}</a>
                </nav>

                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('marketing.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sq' ? 'en' : 'sq' }}">
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                            {{ __('marketing.language_toggle') }}
                        </button>
                    </form>

                    <a href="{{ route('operator.register') }}"
                       class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
                        {{ __('marketing.nav_start_trial') }}
                    </a>
                </div>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-blue-50 via-white to-white">
        <div class="pointer-events-none absolute inset-x-0 -top-40 h-96 bg-[radial-gradient(60%_60%_at_50%_0%,rgba(37,99,235,0.12),transparent)]"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-28 pb-16 text-center">
            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                {{ __('marketing.hero_badge') }}
            </span>

            <h1 class="mt-6 text-4xl sm:text-6xl font-bold tracking-tight text-gray-900 max-w-3xl mx-auto">
                {{ __('marketing.hero_heading') }}
            </h1>
            <p class="mt-6 text-lg text-gray-600 max-w-2xl mx-auto">
                {{ __('marketing.hero_subheading') }}
            </p>

            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('operator.register') }}"
                   class="w-full sm:w-auto rounded-md bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-colors">
                    {{ __('marketing.hero_cta_primary') }}
                </a>
                <a href="#pricing"
                   class="w-full sm:w-auto rounded-md border border-gray-300 bg-white px-6 py-3 text-base font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('marketing.hero_cta_secondary') }}
                </a>
            </div>
            <p class="mt-4 text-sm text-gray-500">{{ __('marketing.hero_note') }}</p>

            {{-- Browser mockup --}}
            <div class="mt-16 mx-auto max-w-4xl rounded-xl border border-gray-200 bg-white shadow-2xl shadow-gray-900/10 overflow-hidden text-left">
                <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3">
                    <span class="h-3 w-3 rounded-full bg-red-300"></span>
                    <span class="h-3 w-3 rounded-full bg-amber-300"></span>
                    <span class="h-3 w-3 rounded-full bg-green-300"></span>
                    <span class="ml-4 flex-1 max-w-sm rounded-md bg-white border border-gray-200 px-3 py-1 text-xs text-gray-400">
                        {{ __('marketing.hero_mock_url') }}
                    </span>
                </div>
                <div class="p-6 grid grid-cols-3 gap-4">
                    <div class="col-span-3 h-20 rounded-lg bg-gradient-to-r from-blue-600 to-blue-500"></div>
                    @for ($i = 0; $i < 6; $i++)
                        <div class="rounded-lg border border-gray-100 p-3">
                            <div class="aspect-video rounded bg-gray-100"></div>
                            <div class="mt-2 h-2.5 w-3/4 rounded bg-gray-200"></div>
                            <div class="mt-1.5 h-2.5 w-1/2 rounded bg-gray-100"></div>
                            <div class="mt-3 h-6 w-20 rounded bg-blue-100"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="border-y border-gray-100 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 grid sm:grid-cols-3 gap-8 text-center">
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ __('marketing.stat_setup_value') }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketing.stat_setup_label') }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ __('marketing.stat_languages_value') }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketing.stat_languages_label') }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ __('marketing.stat_commission_value') }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketing.stat_commission_label') }}</p>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-gray-900">{{ __('marketing.features_heading') }}</h2>
            <p class="mt-4 text-gray-600">{{ __('marketing.features_subheading') }}</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-8">
            @foreach ([
                ['key' => 'storefront', 'icon' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418'],
                ['key' => 'fleet', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                ['key' => 'automation', 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
                ['key' => 'dashboard', 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
            ] as $feature)
                <div class="rounded-xl border border-gray-200 p-8 hover:border-blue-200 hover:shadow-md transition-all">
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 mb-5">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $feature['icon'] }}"/>
                        </svg>
                    </span>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('marketing.feature_' . $feature['key'] . '_title') }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ __('marketing.feature_' . $feature['key'] . '_body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="bg-gray-50 border-y border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-gray-900">{{ __('marketing.how_heading') }}</h2>
                <p class="mt-4 text-gray-600">{{ __('marketing.how_subheading') }}</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-8">
                @foreach ([1, 2, 3] as $step)
                    <div class="relative bg-white rounded-xl border border-gray-200 p-8">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white font-bold mb-5">
                            {{ $step }}
                        </span>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('marketing.how_step_' . $step . '_title') }}</h3>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ __('marketing.how_step_' . $step . '_body') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-gray-900">{{ __('marketing.pricing_heading') }}</h2>
            <p class="mt-4 text-gray-600">{{ __('marketing.pricing_subheading') }}</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 items-start">
            {{-- Trial --}}
            <div class="rounded-xl border border-gray-200 p-6 flex flex-col h-full">
                <h3 class="text-base font-semibold text-gray-900">{{ __('marketing.plan_trial_name') }}</h3>
                <p class="mt-4">
                    <span class="text-3xl font-bold text-gray-900">{{ __('marketing.plan_trial_price') }}</span>
                </p>
                <p class="text-sm text-gray-500">{{ __('marketing.plan_trial_period') }}</p>
                <p class="mt-3 text-sm text-gray-600">{{ __('marketing.plan_trial_tagline') }}</p>

                <ul class="mt-6 space-y-2 text-sm text-gray-600 flex-1">
                    <li>✓ {{ __('marketing.plan_feature_storefront') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_dashboard') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_agreements') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_bilingual') }}</li>
                </ul>

                <a href="{{ route('operator.register') }}"
                   class="mt-6 block text-center rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('marketing.plan_cta') }}
                </a>
            </div>

            {{-- Basic --}}
            <div class="rounded-xl border border-gray-200 p-6 flex flex-col h-full">
                <h3 class="text-base font-semibold text-gray-900">{{ __('marketing.plan_basic_name') }}</h3>
                <p class="mt-4">
                    <span class="text-3xl font-bold text-gray-900">€15</span>
                    <span class="text-sm text-gray-500">{{ __('marketing.plan_basic_period') }}</span>
                </p>
                <p class="mt-3 text-sm text-gray-600">{{ __('marketing.plan_basic_tagline') }}</p>

                <ul class="mt-6 space-y-2 text-sm text-gray-600 flex-1">
                    <li>✓ {{ __('marketing.plan_feature_storefront') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_dashboard') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_agreements') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_bilingual') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_support_email') }}</li>
                </ul>

                <a href="{{ route('operator.register') }}"
                   class="mt-6 block text-center rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('marketing.plan_cta') }}
                </a>
            </div>

            {{-- Standard (highlighted) --}}
            <div class="rounded-xl border-2 border-blue-600 p-6 flex flex-col h-full relative shadow-lg shadow-blue-600/10">
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white">
                    {{ __('marketing.plan_standard_badge') }}
                </span>
                <h3 class="text-base font-semibold text-gray-900">{{ __('marketing.plan_standard_name') }}</h3>
                <p class="mt-4">
                    <span class="text-3xl font-bold text-gray-900">€29</span>
                    <span class="text-sm text-gray-500">{{ __('marketing.plan_standard_period') }}</span>
                </p>
                <p class="mt-3 text-sm text-gray-600">{{ __('marketing.plan_standard_tagline') }}</p>

                <ul class="mt-6 space-y-2 text-sm text-gray-600 flex-1">
                    <li>✓ {{ __('marketing.plan_feature_storefront') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_dashboard') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_agreements') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_bilingual') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_support_email') }}</li>
                </ul>

                <a href="{{ route('operator.register') }}"
                   class="mt-6 block text-center rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
                    {{ __('marketing.plan_cta') }}
                </a>
            </div>

            {{-- Pro --}}
            <div class="rounded-xl border border-gray-200 p-6 flex flex-col h-full">
                <h3 class="text-base font-semibold text-gray-900">{{ __('marketing.plan_pro_name') }}</h3>
                <p class="mt-4">
                    <span class="text-3xl font-bold text-gray-900">€49</span>
                    <span class="text-sm text-gray-500">{{ __('marketing.plan_pro_period') }}</span>
                </p>
                <p class="mt-3 text-sm text-gray-600">{{ __('marketing.plan_pro_tagline') }}</p>

                <ul class="mt-6 space-y-2 text-sm text-gray-600 flex-1">
                    <li>✓ {{ __('marketing.plan_feature_storefront') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_dashboard') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_agreements') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_bilingual') }}</li>
                    <li>✓ {{ __('marketing.plan_feature_support_priority') }}</li>
                </ul>

                <a href="{{ route('operator.register') }}"
                   class="mt-6 block text-center rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('marketing.plan_cta') }}
                </a>
            </div>
        </div>
    </section>

    {{-- CTA band --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-blue-700 to-blue-600">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_100%_at_50%_0%,rgba(255,255,255,0.12),transparent)]"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h2 class="text-3xl font-bold text-white">{{ __('marketing.cta_heading') }}</h2>
            <p class="mt-3 text-blue-100">{{ __('marketing.cta_subheading') }}</p>
            <a href="{{ route('operator.register') }}"
               class="mt-8 inline-block rounded-md bg-white px-6 py-3 text-base font-semibold text-blue-700 hover:bg-blue-50 transition-colors">
                {{ __('marketing.cta_button') }}
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-gray-100 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid sm:grid-cols-3 gap-10 text-sm">
                <div>
                    <p class="font-semibold text-gray-900">{{ config('app.name') }}</p>
                    <p class="mt-2 text-gray-500 max-w-xs">{{ __('marketing.footer_tagline') }}</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">{{ __('marketing.footer_product') }}</p>
                    <ul class="mt-2 space-y-2 text-gray-500">
                        <li><a href="#features" class="hover:text-gray-900">{{ __('marketing.nav_features') }}</a></li>
                        <li><a href="#how-it-works" class="hover:text-gray-900">{{ __('marketing.nav_how_it_works') }}</a></li>
                        <li><a href="#pricing" class="hover:text-gray-900">{{ __('marketing.nav_pricing') }}</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">{{ __('marketing.footer_get_started') }}</p>
                    <ul class="mt-2 space-y-2 text-gray-500">
                        <li><a href="{{ route('operator.register') }}" class="hover:text-gray-900">{{ __('marketing.nav_start_trial') }}</a></li>
                    </ul>
                </div>
            </div>

            <p class="mt-10 border-t border-gray-200 pt-6 text-sm text-gray-400">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>
        </div>
    </footer>
</body>
</html>
