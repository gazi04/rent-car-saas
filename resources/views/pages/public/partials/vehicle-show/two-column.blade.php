{{-- Two-Column Layout: wide content column + narrow booking sidebar. --}}
<div class="space-y-8">
    @include('pages.public.partials.vehicle-show._header')

    <div class="grid lg:grid-cols-3 gap-8 items-start">
        <div class="lg:col-span-2 space-y-8">
            @include('pages.public.partials.vehicle-show._gallery')
            @include('pages.public.partials.vehicle-show._specs')
            @include('pages.public.partials.vehicle-show._description')
        </div>

        <div class="lg:sticky lg:top-24">
            @include('pages.public.partials.vehicle-show._rates')
        </div>
    </div>
</div>
