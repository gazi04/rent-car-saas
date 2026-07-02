{{-- Split Screen Layout: gallery left, details right side-by-side. --}}
<div class="space-y-8">
    @include('pages.public.partials.vehicle-show._header')

    <div class="grid lg:grid-cols-2 gap-8 items-start">
        <div class="lg:sticky lg:top-24">
            @include('pages.public.partials.vehicle-show._gallery')
        </div>

        <div class="space-y-8">
            @include('pages.public.partials.vehicle-show._rates')
            @include('pages.public.partials.vehicle-show._specs')
            @include('pages.public.partials.vehicle-show._description')
        </div>
    </div>
</div>
