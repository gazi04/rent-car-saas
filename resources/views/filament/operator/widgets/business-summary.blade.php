{{--
    Latest AI business summary + "Generate now" header action. Gated by
    BusinessSummaryWidget::canView() — never rendered without the plan feature.
--}}
@php
    $summary = $this->latestSummary();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('panel.ai_summary_heading') }}
        </x-slot>

        <x-slot name="afterHeader">
            <x-filament::button
                wire:click="generate"
                wire:loading.attr="disabled"
                icon="heroicon-m-sparkles"
                size="sm"
            >
                {{ __('panel.ai_generate_now') }}
            </x-filament::button>
        </x-slot>

        @if ($summary)
            <p class="whitespace-pre-line text-sm leading-6 text-gray-950 dark:text-white">
                {{ $summary->content }}
            </p>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                {{ __('panel.ai_summary_period', [
                    'start' => $summary->period_start->translatedFormat('d M Y'),
                    'end' => $summary->period_end->translatedFormat('d M Y'),
                ]) }}
                · {{ $summary->created_at->diffForHumans() }}
            </p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.ai_summary_empty') }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
