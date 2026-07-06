{{--
    Custom wrapper around saade/filament-fullcalendar's stock widget view:
    adds a vehicle filter and a status-color legend above the calendar.
    The x-data block must stay in sync with the plugin's fullcalendar.blade.php.
--}}
@php
    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
@endphp

{{--
    The widget wrapper must be the component's single root element — Livewire renders
    only the first top-level element, so a sibling <style> above it would swallow the
    whole calendar. Keep the <style> nested inside.
--}}
<x-filament-widgets::widget>
    <style>
        /* Diagonal hatch over the flat gray fill so a blocked date reads as
           "unavailable" at a glance, not just another (differently-colored) booking. */
        .fc-event-blocked {
            background-image: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.35) 0 4px, transparent 4px 8px);
        }

        .dark .fc-list-event:hover td {
            background-color: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        html.dark .filament-fullcalendar {
            --fc-page-bg-color: color-mix(in oklab, var(--color-white) 10%, transparent);
        }
    </style>

    <x-filament::section icon="heroicon-o-calendar-days">
        <x-slot name="heading">
            {{ __('panel.availability_calendar') }}
        </x-slot>

        <x-slot name="headerEnd">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Vehicle filter --}}
                <div class="relative w-full sm:w-56">
                    <label for="calendar-vehicle-filter" class="sr-only">{{ __('panel.vehicle') }}</label>
                    <x-filament::icon
                        icon="heroicon-m-truck"
                        class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-gray-400 dark:text-gray-500"
                    />
                    <select
                        id="calendar-vehicle-filter"
                        wire:model.live="vehicleFilter"
                        class="fi-select-input block w-full appearance-none rounded-lg border-none bg-white py-2 ps-9 pe-10 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    >
                        <option value="">{{ __('panel.all_vehicles') }}</option>
                        @foreach ($this->vehicleOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <div
                        wire:loading
                        wire:target="vehicleFilter"
                        class="pointer-events-none absolute inset-y-0 end-9 my-auto h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-primary-600 dark:border-gray-600 dark:border-t-primary-500"
                    ></div>
                    <x-filament::icon
                        icon="heroicon-m-chevron-up-down"
                        class="pointer-events-none absolute inset-y-0 end-3 my-auto h-5 w-5 text-gray-400 dark:text-gray-500"
                    />
                </div>

                <x-filament::actions :actions="$this->getCachedHeaderActions()" class="shrink-0" />
            </div>
        </x-slot>

        {{-- Legend --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @foreach (\App\Enums\BookingStatus::blocking() as $status)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                    <span class="h-2.5 w-2.5 rounded-full" style="background:{{ $status->calendarColor() }}"></span>
                    {{ __('panel.legend_'.$status->value) }}
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                <span class="h-2.5 w-2.5 rounded-full fc-event-blocked" style="background-color:#9ca3af"></span>
                {{ __('panel.legend_blocked') }}
            </span>
            <span class="ms-auto hidden items-center gap-1.5 text-xs text-gray-400 sm:inline-flex dark:text-gray-500">
                <x-filament::icon icon="heroicon-m-information-circle" class="h-3.5 w-3.5" />
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
