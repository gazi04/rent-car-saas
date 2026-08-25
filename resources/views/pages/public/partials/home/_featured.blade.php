@if ($vehicles->isNotEmpty())
    <section>
        <x-ui.section-heading>
            {{ __('booking.featured_vehicles') }}

            <x-slot:action>
                <a href="{{ route('public.vehicles') }}"
                   class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:underline">
                    {{ __('booking.view_all_vehicles') }} <span aria-hidden="true">&rarr;</span>
                </a>
            </x-slot:action>
        </x-ui.section-heading>

        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($vehicles as $vehicle)
                <div wire:key="featured-{{ $vehicle->id }}">
                    @include('pages.public.partials.vehicles._card', ['vehicle' => $vehicle])
                </div>
            @endforeach
        </div>
    </section>
@endif
