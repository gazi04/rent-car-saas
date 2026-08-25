{{-- Always centred on the brand gradient. The old $align/$onDark props existed
     only to serve the seven layout variants and went with them. --}}
<div class="mx-auto max-w-2xl text-center">
    <h1 class="text-4xl font-bold tracking-tight text-ink-inverse sm:text-5xl">
        {{ $content['hero_heading'] }}
    </h1>
    <p class="mt-5 text-lg text-ink-inverse/85">
        {{ $content['hero_subheading'] }}
    </p>
    <div class="mt-8 flex justify-center">
        <x-ui.button :href="route('public.vehicles')" variant="on-dark" size="lg" class="w-full sm:w-auto">
            {{ $content['hero_cta'] }}
        </x-ui.button>
    </div>
</div>
