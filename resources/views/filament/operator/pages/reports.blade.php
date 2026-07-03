<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    @php
        $counts = $this->bookingCounts();
        $utilisation = $this->utilisation();
    @endphp

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.total_bookings') }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $counts['total'] }}</p>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.revenue') }}</p>
            <p class="mt-1 text-2xl font-semibold">€{{ number_format($this->revenue(), 2) }}</p>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.completed') }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $counts['completed'] ?? 0 }}</p>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.cancelled') }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $counts['cancelled'] ?? 0 }}</p>
        </div>
    </div>

    {{-- Per-status breakdown --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="mb-4 text-base font-semibold">{{ __('reports.by_status') }}</h2>
        <div class="flex flex-wrap gap-4">
            @foreach (\App\Enums\BookingStatus::cases() as $status)
                <div class="flex items-center gap-2 text-sm">
                    <span class="font-medium">{{ $status->getLabel() }}:</span>
                    <span>{{ $counts[$status->value] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Utilisation --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="mb-4 text-base font-semibold">{{ __('reports.utilisation') }}</h2>

        @if ($utilisation->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('reports.no_vehicles') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="pb-2 font-medium">{{ __('reports.vehicle') }}</th>
                        <th class="pb-2 font-medium">{{ __('reports.booked_days') }}</th>
                        <th class="pb-2 font-medium">{{ __('reports.utilisation_percent') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($utilisation as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="py-2">{{ $row['vehicle'] }}</td>
                            <td class="py-2">{{ $row['booked_days'] }}</td>
                            <td class="py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $row['percent'] }}%"></div>
                                    </div>
                                    <span>{{ $row['percent'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
