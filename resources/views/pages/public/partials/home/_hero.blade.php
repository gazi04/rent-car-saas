{{-- Always centred on the brand gradient. The old $align/$onDark props existed
     only to serve the seven layout variants and went with them. --}}
<div class="mx-auto max-w-2xl text-center">
    <h1 class="text-3xl font-bold tracking-tight text-balance break-words text-ink-inverse sm:text-4xl md:text-5xl">
        {{ $content['hero_heading'] }}
    </h1>
    <p class="mt-4 text-base text-ink-inverse/85 sm:mt-5 sm:text-lg">
        {{ $content['hero_subheading'] }}
    </p>
    <div class="mt-8 flex justify-center">
        <x-ui.button :href="route('public.vehicles')" variant="on-dark" size="lg" class="w-full sm:w-auto">
            {{ $content['hero_cta'] }}
        </x-ui.button>
    </div>
</div>
