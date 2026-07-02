{{-- Z-Shape Layout: alternating left/right blocks guide the eye in a Z pattern. --}}
<div class="space-y-20 py-8">
    <section class="grid lg:grid-cols-2 gap-12 items-center">
        @include('pages.public.partials.home._hero', ['align' => 'left'])
        <div class="hidden lg:block">
            @if ($vehicles->isNotEmpty())
                @include('pages.public.partials.vehicles._card', ['vehicle' => $vehicles->first()])
            @else
                <div class="rounded-lg bg-gradient-to-br from-primary to-secondary aspect-video"></div>
            @endif
        </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-12 items-center">
        <div class="order-last lg:order-first hidden lg:block rounded-lg bg-primary/5 border border-primary/10 p-10">
        {{-- todo: this 02 section doesn't look good in the website --}}
            <p class="text-5xl font-black text-primary/20">02</p>
        </div>
        @include('pages.public.partials.home._services', ['stacked' => true])
    </section>

    <section class="grid lg:grid-cols-2 gap-12 items-center">
        @include('pages.public.partials.home._about')
        <div class="hidden lg:flex justify-end">
            <a href="{{ route('public.vehicles') }}"
               class="inline-flex items-center rounded-md bg-primary px-6 py-3 text-base font-semibold text-white hover:bg-secondary transition-colors">
                {{ __('booking.view_all_vehicles') }}
            </a>
        </div>
    </section>

    <section>
        @include('pages.public.partials.home._featured')
    </section>
</div>
