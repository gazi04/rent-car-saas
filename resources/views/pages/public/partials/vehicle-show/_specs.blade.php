{{-- Specifications grid: enum specs + operator custom fields. --}}
<div>
    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('booking.specs_heading') }}</h2>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
        <div>
            <dt class="text-gray-500">{{ __('booking.spec_category') }}</dt>
            <dd class="font-medium text-gray-900">{{ $vehicle->category->getLabel() }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">{{ __('booking.spec_fuel') }}</dt>
            <dd class="font-medium text-gray-900">{{ $vehicle->fuel_type->getLabel() }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">{{ __('booking.spec_transmission') }}</dt>
            <dd class="font-medium text-gray-900">{{ $vehicle->transmission->getLabel() }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">{{ __('booking.spec_seats') }}</dt>
            <dd class="font-medium text-gray-900">{{ $vehicle->seats }}</dd>
        </div>

        @foreach ($vehicle->custom_fields ?? [] as $field)
            @if (($field['label'] ?? '') !== '' && ($field['value'] ?? '') !== '')
                <div>
                    <dt class="text-gray-500">{{ $field['label'] }}</dt>
                    <dd class="font-medium text-gray-900">{{ $field['value'] }}</dd>
                </div>
            @endif
        @endforeach
    </dl>
</div>
