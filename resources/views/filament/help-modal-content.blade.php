@if (Lang::has("help.{$key}.body"))
    @foreach (__("help.{$key}.body") as $paragraph)
        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">{{ $paragraph }}</p>
    @endforeach

    @if ($key === 'templates')
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach (config('templates.variables', []) as $variable)
                <code class="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ '{'.$variable.'}' }}</code>
            @endforeach
        </div>
    @endif
@else
    <p class="text-sm text-gray-500">{{ __('help.fallback_body') }}</p>
@endif
