{{-- Asymmetrical Layout: offset columns and staggered blocks for visual tension. --}}
<div class="space-y-20 py-8">
    <section class="grid lg:grid-cols-12 gap-12">
        <div class="lg:col-span-7">
            @include('pages.public.partials.home._hero', ['align' => 'left'])
        </div>
        <div class="lg:col-span-5 lg:mt-24">
            <div class="rounded-2xl bg-primary/5 border border-primary/10 p-8">
                @include('pages.public.partials.home._about')
            </div>
        </div>
    </section>

    <section class="grid lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 lg:col-start-5">
            @include('pages.public.partials.home._services', ['stacked' => true])
        </div>
    </section>

    <section>
        @include('pages.public.partials.home._featured')
    </section>
</div>
