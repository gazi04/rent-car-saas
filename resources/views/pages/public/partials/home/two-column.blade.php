{{-- Two-Column Layout: hero beside services, then stacked sections. --}}
<div class="space-y-20 py-8">
    <section class="grid lg:grid-cols-2 gap-12 items-center">
        @include('pages.public.partials.home._hero', ['align' => 'left'])
        @include('pages.public.partials.home._services', ['stacked' => true, 'showHeading' => false])
    </section>

    <section>
        @include('pages.public.partials.home._featured')
    </section>

    <section>
        @include('pages.public.partials.home._about')
    </section>
</div>
