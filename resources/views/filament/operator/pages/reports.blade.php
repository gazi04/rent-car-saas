<x-filament-panels::page>
    <x-filament::section :heading="__('reports.date_range')" compact>
        <form>
            {{ $this->form }}
        </form>
    </x-filament::section>

    @php
        $counts = $this->bookingCounts();
        $utilisation = $this->utilisation();
    @endphp

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
    </div>
</x-filament-panels::page>
