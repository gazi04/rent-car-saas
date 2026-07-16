<x-filament-panels::page>
    <x-filament::section :heading="__('reports.date_range')" compact>
        <form>
            {{ $this->form }}
        </form>
    </x-filament::section>

    @php
        $counts = $this->bookingCounts();
        $utilisation = $this->utilisation();
        $heatmap = $this->heatmap();
    @endphp

    <style>
        /* Same diagonal hatch as the availability calendar's blocked events, so a
           blocked date reads as "unavailable" rather than just another grey fill —
           Completed bookings are a near-identical grey. Duplicated rather than
           shared: three lines aren't worth coupling this page to a widget's view. */
        .heatmap-cell-blocked {
            background-image: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.35) 0 3px, transparent 3px 6px);
        }
    </style>

    <div wire:loading.class="opacity-50 pointer-events-none" wire:target="data.start_date, data.end_date" class="space-y-6 transition-opacity">
        @if ($counts['total'] === 0)
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.no_bookings') }}</p>
            </div>
        @endif

        {{-- Summary stats --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-filament::section compact>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-8 w-8 shrink-0 text-gray-400 dark:text-gray-500" />
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.total_bookings') }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $counts['total'] }}</p>
                    </div>
                </div>
            </x-filament::section>
            <x-filament::section compact>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-banknotes" class="h-8 w-8 shrink-0 text-success-500" />
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.revenue') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-success-600 dark:text-success-400">€{{ number_format($this->revenue(), 2) }}</p>
                    </div>
                </div>
            </x-filament::section>
            <x-filament::section compact>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 shrink-0 text-success-500" />
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.completed') }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $counts['completed'] ?? 0 }}</p>
                    </div>
                </div>
            </x-filament::section>
            <x-filament::section compact>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-x-circle" class="h-8 w-8 shrink-0 text-danger-500" />
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.cancelled') }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $counts['cancelled'] ?? 0 }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Per-status breakdown --}}
        <x-filament::section :heading="__('reports.by_status')">
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\BookingStatus::cases() as $status)
                    <x-filament::badge :color="$status->getColor()">
                        {{ $status->getLabel() }}: {{ $counts[$status->value] ?? 0 }}
                    </x-filament::badge>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Utilisation --}}
        <x-filament::section :heading="__('reports.utilisation')">
            @if ($utilisation->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.no_vehicles') }}</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="w-full text-sm border-red-500">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                <th class="px-4 py-2.5">{{ __('reports.vehicle') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('reports.booked_days') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('reports.utilisation_percent') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($utilisation as $row)
                                @php
                                    $barColorClass = match ($this->utilisationColor($row['percent'])) {
                                        'danger' => 'bg-danger-500',
                                        'warning' => 'bg-warning-500',
                                        default => 'bg-success-500',
                                    };
                                @endphp
                                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['vehicle'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $row['booked_days'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <div class="h-2 w-full max-w-32 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                                <div class="h-full rounded-full {{ $barColorClass }} transition-all" style="width: {{ $row['percent'] }}%"></div>
                                            </div>
                                            <span class="w-11 shrink-0 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['percent'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Occupancy heatmap --}}
        <x-filament::section :heading="__('reports.heatmap')">
            @if ($heatmap['truncated'])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.heatmap_range_too_long') }}</p>
            @elseif (empty($heatmap['rows']))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.no_vehicles') }}</p>
            @else
                {{-- Legend: the only thing separating a Completed booking's grey
                     from a blocked date's grey, which differ solely by hatching. --}}
                <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-600 dark:text-gray-300">
                    @foreach ([
                        \App\Enums\BookingStatus::Pending->calendarColor() => __('panel.legend_pending'),
                        \App\Enums\BookingStatus::Confirmed->calendarColor() => __('panel.legend_confirmed'),
                        \App\Enums\BookingStatus::Active->calendarColor() => __('panel.legend_active'),
                        \App\Enums\BookingStatus::Completed->calendarColor() => __('reports.legend_completed'),
                    ] as $color => $label)
                        <span class="flex items-center gap-1.5">
                            <span class="h-3 w-3 shrink-0 rounded-sm" style="background-color: {{ $color }}"></span>
                            {{ $label }}
                        </span>
                    @endforeach
                    <span class="flex items-center gap-1.5">
                        <span class="heatmap-cell-blocked h-3 w-3 shrink-0 rounded-sm" style="background-color: #9ca3af"></span>
                        {{ __('panel.legend_blocked') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 shrink-0 rounded-sm bg-gray-100 ring-1 ring-inset ring-gray-950/10 dark:bg-white/5 dark:ring-white/10"></span>
                        {{ __('reports.heatmap_free') }}
                    </span>
                </div>

                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                <th class="sticky start-0 z-10 bg-gray-50 px-4 py-2.5 text-start ring-1 ring-inset ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    {{ __('reports.vehicle') }}
                                </th>
                                @foreach ($heatmap['days'] as $day)
                                    <th @class([
                                        'w-7 px-0 py-2 text-center font-normal tabular-nums',
                                        'bg-gray-100/70 dark:bg-white/[0.03]' => $day['is_weekend'],
                                    ]) title="{{ $day['date'] }}">
                                        {{ $day['day'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($heatmap['rows'] as $row)
                                <tr>
                                    <td class="sticky start-0 z-10 whitespace-nowrap bg-white px-4 py-1.5 font-medium text-gray-950 ring-1 ring-inset ring-gray-950/5 dark:bg-gray-900 dark:text-white dark:ring-white/10">
                                        {{ $row['vehicle'] }}
                                    </td>
                                    @foreach ($row['cells'] as $cell)
                                        <td class="p-px">
                                            <div @class([
                                                'h-6 w-full rounded-sm',
                                                'bg-gray-100 dark:bg-white/5' => $cell['kind'] === 'free',
                                                'heatmap-cell-blocked' => $cell['kind'] === 'block',
                                            ]) @if ($cell['color']) style="background-color: {{ $cell['color'] }}" @endif
                                                @if ($cell['label']) title="{{ $cell['label'] }}" @endif></div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        {{-- Fleet demand: the column-wise reduction of the grid above.
                             This is the layer where intensity is meaningful — a single
                             vehicle-day is binary, but "how much of the fleet is out" is not. --}}
                        <tfoot>
                            <tr>
                                <td class="sticky start-0 z-10 whitespace-nowrap bg-white px-4 py-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 ring-1 ring-inset ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                                    {{ __('reports.heatmap_demand') }}
                                </td>
                                @foreach ($heatmap['demand'] as $day)
                                    <td class="p-px">
                                        <div class="flex h-6 w-full items-center justify-center rounded-sm text-[10px] font-semibold tabular-nums text-gray-700 dark:text-gray-200 {{ $this->demandClass($day['percent']) }}"
                                            title="{{ $day['date'] }} — {{ $day['occupied'] }}/{{ $heatmap['fleet_size'] }} ({{ $day['percent'] }}%)">
                                            {{ $day['occupied'] > 0 ? $day['occupied'] : '' }}
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
