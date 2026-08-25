<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/*
 * Smoke + contract tests for the shared storefront primitives in
 * resources/views/components/ui/.
 *
 * The passthrough tests are the important ones: three flatpickr entrypoints and
 * every browser test in tests/Browser/Public locate their targets by id or
 * data-test attribute set at the call site. If a control component ever starts
 * filtering attributes, those break silently and far away from the cause.
 */

it('renders every ui primitive without error', function (string $template) {
    expect(Blade::render($template))->not->toBeEmpty();
})->with([
    'container' => ['<x-ui.container>body</x-ui.container>'],
    'container narrow' => ['<x-ui.container size="narrow">body</x-ui.container>'],
    'button' => ['<x-ui.button>Go</x-ui.button>'],
    'button link' => ['<x-ui.button href="/x" variant="secondary">Go</x-ui.button>'],
    'input' => ['<x-ui.input type="text" />'],
    'select' => ['<x-ui.select><option>a</option></x-ui.select>'],
    'textarea' => ['<x-ui.textarea>hi</x-ui.textarea>'],
    'field' => ['<x-ui.field label="Name" for="n" required><x-ui.input id="n" /></x-ui.field>'],
    'card' => ['<x-ui.card>content</x-ui.card>'],
    'section heading' => ['<x-ui.section-heading subheading="sub">Title</x-ui.section-heading>'],
    'badge' => ['<x-ui.badge tone="positive">New</x-ui.badge>'],
    'stars' => ['<x-ui.stars :rating="4" />'],
    'price' => ['<x-ui.price :amount="36" per="per day" />'],
    'alert' => ['<x-ui.alert tone="critical">Boom</x-ui.alert>'],
    'empty state' => ['<x-ui.empty-state icon="truck" title="Nothing">none</x-ui.empty-state>'],
]);

it('passes arbitrary attributes through the control primitives untouched', function (string $template) {
    $html = Blade::render($template);

    expect($html)
        ->toContain('id="date-range-picker"')
        ->toContain('data-availability-url="/avail"')
        ->toContain('data-test="customer-name"')
        ->toContain('wire:model="customerName"');
})->with([
    'input' => ['<x-ui.input id="date-range-picker" data-availability-url="/avail" data-test="customer-name" wire:model="customerName" />'],
    'textarea' => ['<x-ui.textarea id="date-range-picker" data-availability-url="/avail" data-test="customer-name" wire:model="customerName" />'],
    'select' => ['<x-ui.select id="date-range-picker" data-availability-url="/avail" data-test="customer-name" wire:model="customerName"></x-ui.select>'],
]);

it('keeps its own classes when the call site adds more', function () {
    $html = Blade::render('<x-ui.input class="uppercase" />');

    expect($html)->toContain('uppercase')
        ->and($html)->toContain('rounded-control');
});

it('renders a button as an anchor only when given an href', function () {
    expect(Blade::render('<x-ui.button href="/go">Go</x-ui.button>'))->toContain('<a href="/go"')
        ->and(Blade::render('<x-ui.button type="submit">Go</x-ui.button>'))->toContain('<button type="submit"');
});

it('gives interactive buttons a 44px minimum tap target', function () {
    // WCAG 2.5.5. The browser suite asserts the rendered size; this asserts the
    // class that produces it, so a regression fails in the fast suite too.
    expect(Blade::render('<x-ui.button>Go</x-ui.button>'))->toContain('min-h-11');
});

it('clamps a star rating into the 0-5 range', function () {
    expect(Blade::render('<x-ui.stars :rating="9" />'))->toContain(str_repeat('★', 5))
        ->and(Blade::render('<x-ui.stars :rating="-3" />'))->toContain('aria-label');
});

it('renders the currency symbol from lang, not hardcoded markup', function () {
    expect(Blade::render('<x-ui.price :amount="36.5" />'))
        ->toContain(__('booking.currency_symbol').'36.50');
});

it('marks a critical alert with role=alert so screen readers announce it', function () {
    expect(Blade::render('<x-ui.alert tone="critical">x</x-ui.alert>'))->toContain('role="alert"')
        ->and(Blade::render('<x-ui.alert tone="notice">x</x-ui.alert>'))->toContain('role="status"');
});

it('renders a field error when the bag holds one for that name', function () {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['customerEmail' => ['Bad email.']]));

    // Shared, not passed as view data: @error resolves the bag from shared view
    // state, which is what ShareErrorsFromSession does in a real request. Passing
    // it locally does not reach a nested component.
    View::share('errors', $errors);

    $html = Blade::render('<x-ui.field name="customerEmail" label="Email"><x-ui.input /></x-ui.field>');

    expect($html)->toContain('Bad email.')->toContain('text-critical');
});
