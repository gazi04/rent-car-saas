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
    <header class="border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('home') }}" class="text-lg font-bold text-gray-900">
                    {{ config('app.name') }}
                </a>

                <nav class="hidden sm:flex items-center gap-8 text-sm font-medium text-gray-600">
                    <a href="#features" class="hover:text-gray-900 transition-colors">{{ __('marketing.nav_features') }}</a>
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
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-gray-900 max-w-3xl mx-auto">
            {{ __('marketing.hero_heading') }}
        </h1>
        <p class="mt-6 text-lg text-gray-600 max-w-2xl mx-auto">
            {{ __('marketing.hero_subheading') }}
        </p>

        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('operator.register') }}"
               class="w-full sm:w-auto rounded-md bg-blue-600 px-6 py-3 text-base font-semibold text-white hover:bg-blue-700 transition-colors">
                {{ __('marketing.hero_cta_primary') }}
            </a>
            <a href="#pricing"
               class="w-full sm:w-auto rounded-md border border-gray-300 px-6 py-3 text-base font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                {{ __('marketing.hero_cta_secondary') }}
            </a>
        </div>

        <p class="mt-4 text-sm text-gray-500">{{ __('marketing.hero_note') }}</p>
    </section>

    {{-- Features --}}
    <section id="features" class="bg-gray-50 border-y border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-gray-900">{{ __('marketing.features_heading') }}</h2>
                <p class="mt-4 text-gray-600">{{ __('marketing.features_subheading') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('marketing.feature_storefront_title') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('marketing.feature_storefront_body') }}</p>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('marketing.feature_fleet_title') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('marketing.feature_fleet_body') }}</p>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('marketing.feature_automation_title') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('marketing.feature_automation_body') }}</p>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('marketing.feature_dashboard_title') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('marketing.feature_dashboard_body') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-gray-900">{{ __('marketing.pricing_heading') }}</h2>
            <p class="mt-4 text-gray-600">{{ __('marketing.pricing_subheading') }}</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 items-start">
            {{-- Trial --}}
            <div class="rounded-lg border border-gray-200 p-6 flex flex-col h-full">
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
            <div class="rounded-lg border border-gray-200 p-6 flex flex-col h-full">
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
            <div class="rounded-lg border-2 border-blue-600 p-6 flex flex-col h-full relative">
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
            <div class="rounded-lg border border-gray-200 p-6 flex flex-col h-full">
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
    <section class="bg-blue-600">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h2 class="text-3xl font-bold text-white">{{ __('marketing.cta_heading') }}</h2>
            <p class="mt-3 text-blue-100">{{ __('marketing.cta_subheading') }}</p>
            <a href="{{ route('operator.register') }}"
               class="mt-8 inline-block rounded-md bg-white px-6 py-3 text-base font-semibold text-blue-600 hover:bg-blue-50 transition-colors">
                {{ __('marketing.cta_button') }}
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
            <div>
                <p class="font-medium text-gray-700">{{ config('app.name') }}</p>
                <p class="mt-1">{{ __('marketing.footer_tagline') }}</p>
            </div>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </footer>
</body>
</html>
