{{-- F-Shape Layout: strong left-aligned reading axis, content hugs the left edge. --}}
<div class="space-y-20 py-8">
    <section class="border-l-4 border-primary pl-6 sm:pl-10 py-6">
        @include('pages.public.partials.home._hero', ['align' => 'left'])
    </section>

    <section class="grid lg:grid-cols-3 gap-12">
        <div class="lg:col-span-2">
            @include('pages.public.partials.home._services', ['stacked' => true])
        </div>
        <div>
            @include('pages.public.partials.home._about')
        </div>
    </section>

    <section>
        @include('pages.public.partials.home._featured')
    </section>
</div>
