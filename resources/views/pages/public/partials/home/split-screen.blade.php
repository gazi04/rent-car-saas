{{-- Split Screen Layout: full-bleed 50/50 hero panel + services panel. --}}
<div class="space-y-20">
    <section class="relative left-1/2 w-screen -translate-x-1/2 -mt-8 grid lg:grid-cols-2">
        <div class="bg-gradient-to-br from-primary to-secondary flex items-center">
            <div class="px-6 sm:px-12 py-20 lg:py-28 w-full max-w-2xl ml-auto lg:pr-16">
                @include('pages.public.partials.home._hero', ['align' => 'left', 'onDark' => true])
            </div>
        </div>
        <div class="bg-gray-100 flex items-center">
            <div class="px-6 sm:px-12 py-16 w-full max-w-2xl mr-auto lg:pl-16">
                @include('pages.public.partials.home._services', ['stacked' => true])
            </div>
        </div>
    </section>

    <section>
        @include('pages.public.partials.home._featured')
    </section>

    <section>
        @include('pages.public.partials.home._about')
    </section>
</div>
