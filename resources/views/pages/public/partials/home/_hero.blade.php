@php
    /** Shared hero content. Shells control placement via $align ('center'|'left') and $onDark. */
    $align = $align ?? 'center';
    $onDark = $onDark ?? false;
@endphp

<div class="{{ $align === 'center' ? 'text-center mx-auto' : 'text-left' }} max-w-2xl">
    <h1 class="text-4xl sm:text-5xl font-bold tracking-tight {{ $onDark ? 'text-white' : 'text-gray-900' }}">
        {{ $content['hero_heading'] }}
    </h1>
    <p class="mt-5 text-lg {{ $onDark ? 'text-white/80' : 'text-gray-600' }}">
        {{ $content['hero_subheading'] }}
    </p>
    <div class="mt-8 flex {{ $align === 'center' ? 'justify-center' : 'justify-start' }}">
        <a href="{{ route('public.vehicles') }}"
           class="inline-flex items-center rounded-md px-6 py-3 text-base font-semibold transition-colors {{ $onDark ? 'bg-white text-gray-900 hover:bg-gray-100' : 'bg-primary text-white hover:bg-secondary' }}">
            {{ $content['hero_cta'] }}
        </a>
    </div>
</div>
