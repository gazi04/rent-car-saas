<?php

declare(strict_types=1);

use App\Support\FaqGrounding;

/*
|--------------------------------------------------------------------------
| FaqGrounding — is the concierge's quote really a span of the FAQ?
|--------------------------------------------------------------------------
|
| Two failure directions matter equally here, and only one of them is a
| security bug. Too loose and a fabricated policy reaches a visitor in the
| operator's branding (Finding 5). Too strict and every legitimate answer
| collapses to "contact the operator" and the feature is dead without anything
| going red. The normalization cases below are the second kind.
|
*/

const FAQ = <<<'TXT'
Deposit
A refundable deposit of 200 EUR is held on your card at pickup and released
within 5 working days of return.

Young drivers
Drivers aged 21-24 pay a young-driver surcharge of 8 EUR per day.
We don't rent to drivers under 21.
TXT;

it('accepts a quote copied verbatim from the FAQ', function () {
    expect(FaqGrounding::supports(FAQ, 'Drivers aged 21-24 pay a young-driver surcharge of 8 EUR per day.'))
        ->toBeTrue();
});

it('rejects a quote that does not appear in the FAQ', function () {
    // The finding's failure scenario: confident prose about a policy the
    // operator never wrote, with a citation to match.
    expect(FaqGrounding::supports(FAQ, 'We guarantee a full refund and a free upgrade on request.'))
        ->toBeFalse();
});

it('rejects an empty or missing quote', function (string $quote) {
    expect(FaqGrounding::supports(FAQ, $quote))->toBeFalse();
})->with(['empty' => [''], 'whitespace' => ["  \n "]]);

it('rejects a quote too short to be evidence', function () {
    // "deposit" is in the FAQ, but quoting one word is not reading the source.
    expect(FaqGrounding::supports(FAQ, 'deposit'))->toBeFalse()
        ->and(mb_strlen('deposit'))->toBeLessThan(FaqGrounding::MIN_QUOTE_LENGTH);
});

it('rejects a quote longer than the cap even when it is genuinely in the FAQ', function () {
    // Otherwise a model that quotes the whole FAQ satisfies containment no
    // matter what its answer claimed, and the check means nothing.
    $knowledge = str_repeat('All rentals include unlimited mileage. ', 40);

    expect(mb_strlen($knowledge))->toBeGreaterThan(FaqGrounding::MAX_QUOTE_LENGTH)
        ->and(FaqGrounding::supports($knowledge, $knowledge))->toBeFalse();
});

it('accepts a quote the model reformatted but did not reword', function (string $quote) {
    expect(FaqGrounding::supports(FAQ, $quote))->toBeTrue();
})->with([
    'different case' => ['DRIVERS AGED 21-24 PAY A YOUNG-DRIVER SURCHARGE'],
    'collapsed newline' => ['A refundable deposit of 200 EUR is held on your card at pickup and released within 5 working days'],
    'runs of spaces' => ['Drivers    aged 21-24 pay a     young-driver surcharge'],
    'non-breaking space' => ["Drivers\u{00A0}aged 21-24 pay a young-driver surcharge"],
    'curly apostrophe' => ["We don\u{2019}t rent to drivers under 21."],
    'en dash for hyphen' => ["Drivers aged 21\u{2013}24 pay a young-driver surcharge"],
    'leading ellipsis' => ['...pay a young-driver surcharge of 8 EUR per day'],
    'unicode ellipsis' => ["\u{2026}released within 5 working days of return"],
    'trailing period the source runs past' => ['A refundable deposit of 200 EUR is held on your card.'],
]);

it('does not let normalization collapse distinct text into a match', function () {
    // The normalization is aggressive on purpose; it must not become so
    // aggressive that a different claim starts matching.
    expect(FaqGrounding::supports(FAQ, 'A refundable deposit of 500 EUR is held on your card'))
        ->toBeFalse();
});

it('verifies across languages, which is the whole reason it is a quote', function () {
    // localizedSetting() falls back across languages, so an Albanian answer
    // grounded in an English FAQ is a supported configuration. The quote comes
    // from the source, so it verifies regardless of the answer's language —
    // where any answer-to-source word overlap would score near zero.
    expect(FaqGrounding::supports(FAQ, 'A refundable deposit of 200 EUR is held on your card'))
        ->toBeTrue();
});
