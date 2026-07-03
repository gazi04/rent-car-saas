{{--
    Custom wrapper around saade/filament-fullcalendar's stock widget view:
    adds a vehicle filter and a status-color legend above the calendar.
    The x-data block must stay in sync with the plugin's fullcalendar.blade.php.
--}}
@php
    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div class="w-full sm:w-64">
                <label for="calendar-vehicle-filter" class="fi-fo-field-wrp-label text-sm font-medium leading-6 text-gray-950 dark:text-white">
                    {{ __('panel.vehicle') }}
                </label>
                <select
                    id="calendar-vehicle-filter"
                    wire:model.live="vehicleFilter"
                    class="fi-select-input mt-1 block w-full rounded-lg border-none bg-white py-1.5 pe-8 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                >
                    <option value="">{{ __('panel.all_vehicles') }}</option>
                    @foreach ($this->vehicleOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <x-filament::actions :actions="$this->getCachedHeaderActions()" class="shrink-0" />
        </div>

        {{-- Legend --}}
        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm" style="background:#f59e0b"></span>
                {{ __('panel.legend_pending') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm" style="background:#3b82f6"></span>
                {{ __('panel.legend_confirmed') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm" style="background:#22c55e"></span>
                {{ __('panel.legend_active') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm" style="background:#9ca3af"></span>
                {{ __('panel.legend_blocked') }}
            </span>
            <span class="ms-auto hidden text-gray-400 sm:inline dark:text-gray-500">
                {{ __('panel.calendar_hint') }}
            </span>
        </div>

        <div wire:ignore x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-fullcalendar-alpine', 'saade/filament-fullcalendar') }}"
            x-ignore x-data="fullcalendar({
                locale: @js($plugin->getLocale()),
                plugins: @js($plugin->getPlugins()),
                schedulerLicenseKey: @js($plugin->getSchedulerLicenseKey()),
                timeZone: @js($plugin->getTimezone()),
                config: @js($this->getConfig()),
                editable: @json($plugin->isEditable()),
                selectable: @json($plugin->isSelectable()),
                eventClassNames: {!! htmlspecialchars($this->eventClassNames(), ENT_COMPAT) !!},
                eventContent: {!! htmlspecialchars($this->eventContent(), ENT_COMPAT) !!},
                eventDidMount: {!! htmlspecialchars($this->eventDidMount(), ENT_COMPAT) !!},
                eventWillUnmount: {!! htmlspecialchars($this->eventWillUnmount(), ENT_COMPAT) !!},
            })" class="filament-fullcalendar"></div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
