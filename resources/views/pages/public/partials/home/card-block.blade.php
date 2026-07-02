{{-- Card / Block Layout: every section presented as a distinct card. --}}
<div class="space-y-10 py-8">
    <section class="rounded-2xl bg-gradient-to-br from-primary to-secondary px-6 sm:px-12 py-16 sm:py-24 flex justify-center">
        @include('pages.public.partials.home._hero', ['align' => 'center', 'onDark' => true])
    </section>

    <section>
        @include('pages.public.partials.home._services')
    </section>

    <section class="rounded-2xl bg-white border border-gray-200 p-6 sm:p-10">
        @include('pages.public.partials.home._featured')
    </section>

    <section class="rounded-2xl bg-gray-100 p-6 sm:p-10">
        @include('pages.public.partials.home._about')
    </section>
</div>
