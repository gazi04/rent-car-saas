{{--
    Always-visible "you've hit your plan's cap" notice. Rendered at the top of
    the content area on the vehicle/staff list and create pages via the
    CONTENT_START render hooks in OperatorPanelProvider — only while the tenant
    is actually at the cap. $title and $body are pre-resolved translated strings.
--}}
<div
    role="alert"
    class="mb-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200"
>
    <x-filament::icon
        icon="heroicon-o-exclamation-triangle"
        class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500"
    />

    <div class="text-sm">
        <p class="font-semibold">{{ $title }}</p>
        <p class="mt-0.5">{{ $body }}</p>
    </div>
</div>
