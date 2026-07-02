{{-- Z-Shape Layout: alternating left/right blocks down the page. --}}
<div class="space-y-12">
    @include('pages.public.partials.vehicle-show._header')

    <div class="grid lg:grid-cols-2 gap-8 items-start">
        @include('pages.public.partials.vehicle-show._gallery')
        @include('pages.public.partials.vehicle-show._rates')
    </div>

    <div class="grid lg:grid-cols-2 gap-8 items-start">
        <div class="order-last lg:order-first">
            @include('pages.public.partials.vehicle-show._description')
        </div>
        @include('pages.public.partials.vehicle-show._specs')
    </div>
</div>
