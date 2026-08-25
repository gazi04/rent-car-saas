{{-- Specifications grid: enum specs + operator custom fields.

     Single column on a phone: two columns of label+value inside an already
     narrow card left both sides truncating. --}}
<div>
    <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('booking.specs_heading') }}</h2>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-ink-muted">{{ __('booking.spec_category') }}</dt>
            <dd class="font-medium text-ink">{{ $vehicle->category->getLabel() }}</dd>
        </div>
        <div>
            <dt class="text-ink-muted">{{ __('booking.spec_fuel') }}</dt>
            <dd class="font-medium text-ink">{{ $vehicle->fuel_type->getLabel() }}</dd>
        </div>
        <div>
            <dt class="text-ink-muted">{{ __('booking.spec_transmission') }}</dt>
            <dd class="font-medium text-ink">{{ $vehicle->transmission->getLabel() }}</dd>
        </div>
        <div>
            <dt class="text-ink-muted">{{ __('booking.spec_seats') }}</dt>
            <dd class="font-medium text-ink">{{ $vehicle->seats }}</dd>
        </div>

        @foreach ($vehicle->custom_fields ?? [] as $field)
            @if (($field['label'] ?? '') !== '' && ($field['value'] ?? '') !== '')
                <div>
                    <dt class="text-ink-muted">{{ $field['label'] }}</dt>
                    <dd class="font-medium text-ink">{{ $field['value'] }}</dd>
                </div>
            @endif
        @endforeach
    </dl>
</div>
