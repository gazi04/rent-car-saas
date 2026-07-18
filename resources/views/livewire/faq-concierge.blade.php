<?php

use App\Enums\PlanFeature;
use App\Exceptions\AiRequestFailedException;
use App\Services\Ai\FaqConciergeService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Conversation so far, oldest first. Lives only in component state — passed
     * to the agent as history and gone when the page unloads (no storage).
     *
     * @var list<array{role: string, content: string}>
     */
    public array $messages = [];

    public string $question = '';

    /**
     * Whether this tenant may show the widget at all: the plan feature is on AND
     * the operator actually wrote FAQ content (no content → no widget → no spend,
     * and no bot with nothing to ground on). Recomputed server-side on every
     * hydration, and — critically — re-asserted at the top of ask(), because a
     * public Livewire action has no framework authorization of its own.
     */
    #[Computed]
    public function isEnabled(): bool
    {
        $gated = tenant()?->allowsFeature(PlanFeature::AiConcierge)
            ?? (bool) PlanFeature::AiConcierge->default();

        return $gated && trim((string) (tenant()?->localizedSetting('faq_content', '') ?? '')) !== '';
    }

    public function ask(): void
    {
        // Gate FIRST — the @if wrapping the widget is cosmetic; this method is
        // directly callable over /livewire/update by anyone.
        abort_unless($this->isEnabled, 404);

        $this->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $ipKey = 'concierge-ask:'.tenant()->id.':'.request()->ip();
        $tenantKey = 'concierge-tenant:'.tenant()->id;

        $question = trim($this->question);
        $this->messages[] = ['role' => 'user', 'content' => $question];
        $this->question = '';

        // Two feature-local limiters. On cap, answer with the fallback line —
        // a visitor must never see the operator's budget or an error.
        if (RateLimiter::tooManyAttempts($ipKey, maxAttempts: 10)
            || RateLimiter::tooManyAttempts($tenantKey, maxAttempts: (int) config('ai.concierge_daily_cap', 200))) {
            $this->messages[] = ['role' => 'assistant', 'content' => __('booking.concierge_throttled')];

            return;
        }

        // Hit after validation + the throttle check, so a rejected question never
        // burns an attempt.
        RateLimiter::hit($ipKey, decaySeconds: 3600);
        RateLimiter::hit($tenantKey, decaySeconds: 86400);

        try {
            $answer = app(FaqConciergeService::class)->answer(
                tenant(),
                $question,
                app()->getLocale(),
                array_slice($this->messages, 0, -1),
            );
        } catch (AiRequestFailedException) {
            $this->messages[] = ['role' => 'assistant', 'content' => __('booking.concierge_error')];

            return;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $answer];
    }
}; ?>

<div>
@if ($this->isEnabled)
    <div x-data="{ open: false }" class="fixed bottom-4 end-4 z-50 print:hidden">
        {{-- Launcher --}}
        <button
            type="button"
            x-show="!open"
            x-on:click="open = true"
            class="flex items-center gap-2 rounded-full px-4 py-3 text-white shadow-lg transition hover:opacity-90"
            style="background-color: var(--color-primary, #1f2937);"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <span class="text-sm font-medium">{{ __('booking.concierge_launcher') }}</span>
        </button>

        {{-- Panel --}}
        <div
            x-show="open"
            x-cloak
            x-transition
            class="flex w-80 max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl"
        >
            <div class="flex items-center justify-between px-4 py-3 text-white" style="background-color: var(--color-primary, #1f2937);">
                <span class="text-sm font-semibold">{{ __('booking.concierge_title') }}</span>
                <button type="button" x-on:click="open = false" class="text-white/80 hover:text-white" aria-label="{{ __('booking.concierge_close') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex h-80 flex-col gap-3 overflow-y-auto px-4 py-3 text-sm">
                <p class="rounded-lg bg-gray-100 px-3 py-2 text-gray-600">{{ __('booking.concierge_greeting') }}</p>

                @foreach ($messages as $message)
                    @if ($message['role'] === 'user')
                        <p class="ms-auto max-w-[85%] rounded-lg px-3 py-2 text-white" style="background-color: var(--color-primary, #1f2937);">
                            {{ $message['content'] }}
                        </p>
                    @else
                        <p class="me-auto max-w-[85%] rounded-lg bg-gray-100 px-3 py-2 text-gray-800">
                            {{ $message['content'] }}
                        </p>
                    @endif
                @endforeach

                <p wire:loading wire:target="ask" class="me-auto max-w-[85%] rounded-lg bg-gray-100 px-3 py-2 text-gray-400">
                    {{ __('booking.concierge_thinking') }}
                </p>
            </div>

            <form wire:submit="ask" class="border-t border-gray-200 p-3">
                <div class="flex items-end gap-2">
                    <textarea
                        wire:model="question"
                        rows="1"
                        maxlength="500"
                        placeholder="{{ __('booking.concierge_placeholder') }}"
                        class="min-h-[2.5rem] flex-1 resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="ask"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-50"
                        style="background-color: var(--color-primary, #1f2937);"
                    >
                        {{ __('booking.concierge_send') }}
                    </button>
                </div>
                @error('question')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        </div>
    </div>
@endif
</div>
