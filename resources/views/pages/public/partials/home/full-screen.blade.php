{{-- Full Screen Layout: full-bleed hero banner, then stacked sections. --}}
<div class="space-y-20">
    <section class="relative left-1/2 w-screen -translate-x-1/2 -mt-8 bg-gradient-to-br from-primary to-secondary">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 flex items-center justify-center min-h-[55vh]">
            @include('pages.public.partials.home._hero', ['align' => 'center', 'onDark' => true])
        </div>
    </section>

    <section>
        @include('pages.public.partials.home._services')
    </section>

    <section>
        @include('pages.public.partials.home._featured')
    </section>

    <section>
        @include('pages.public.partials.home._about')
    </section>
</div>
