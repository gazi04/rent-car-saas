<?php

declare(strict_types=1);

use App\Support\BookingReference;

/*
|--------------------------------------------------------------------------
| Booking reference generation
|--------------------------------------------------------------------------
|
| The reference is the only thing standing between an anonymous visitor and the
| customer PII on /booking/{reference}/confirmation, so its shape is a security
| property. The previous generator, Str::upper(Str::random(6)), folded a
| 62-symbol draw onto 36 NON-UNIFORMLY — letters twice as likely as digits — for
| ~30.7 bits rather than the 62^6 the 2026-09-03 review assumed.
|
| These tests pin what replaced it: an explicit alphabet, a uniform draw, and
| enough width that the collision path (which has no retry behind it) stops being
| reachable.
|
*/

it('mints a reference of the documented shape', function () {
    expect(BookingReference::generate())
        ->toMatch('/^BK-\d{4}-[0-9A-HJKMNP-TV-Z]{8}$/');
});

it('never emits characters that are ambiguous when read aloud', function () {
    // A reference gets dictated down a phone line to a rental desk, so 0/O and
    // 1/I collisions are a real support cost. I, L, O and U are excluded.
    $sample = implode('', array_map(
        fn (): string => BookingReference::generate(),
        range(1, 200),
    ));

    expect($sample)->not->toContain('I')
        ->and($sample)->not->toContain('L')
        ->and($sample)->not->toContain('O')
        ->and($sample)->not->toContain('U');
});

it('draws uniformly rather than folding a wider alphabet onto a narrower one', function () {
    // The exact defect being regressed against: Str::upper(Str::random()) gave
    // letters ~2x a digit's probability. Over 2,000 references (16,000 symbols)
    // a 2:1 skew is enormous, while true uniformity puts each of the 32 symbols
    // near 500. A generous band still fails the old implementation decisively.
    $symbols = str_split(str_replace(
        '-',
        '',
        implode('', array_map(
            fn (): string => substr(BookingReference::generate(), 8),
            range(1, 2000),
        )),
    ));

    $counts = array_count_values($symbols);

    $digits = array_sum(array_map(fn (string $d): int => $counts[$d] ?? 0, str_split('23456789')));
    $letters = array_sum(array_map(fn (string $l): int => $counts[$l] ?? 0, str_split('ABCDEFGHJKMNPQRSTVWXYZ')));

    // 8 digits vs 22 letters under a uniform draw => letters/digits ~= 2.75.
    // The old folded implementation would land near 5.5.
    expect($letters / $digits)->toBeLessThan(3.6)
        ->and($letters / $digits)->toBeGreaterThan(2.0);
});

it('does not repeat itself across a large batch', function () {
    // 40 bits: 5,000 draws should collide with probability ~1e-5. A generator that
    // silently lost width would show up here, and a collision in production is not
    // cosmetic — BookingService mints the reference inside the booking transaction
    // with no retry, so a duplicate fails a real customer's booking.
    $references = array_map(fn (): string => BookingReference::generate(), range(1, 5000));

    expect(array_unique($references))->toHaveCount(5000);
});
