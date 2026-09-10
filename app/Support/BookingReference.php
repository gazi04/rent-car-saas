<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mints the customer-facing booking reference (e.g. BK-2026-K7M2PQR9).
 *
 * The reference is not decoration: /booking/{reference}/confirmation is an
 * unauthenticated GET that hands back another party's booking on a correct guess —
 * the vehicle, the rental dates, the total, and whether an email is on file. Its
 * unguessability is therefore a security control, and it was weaker than the
 * codebase believed.
 *
 * The previous implementation was `Str::upper(Str::random(6))`. Str::random draws
 * from a 62-symbol alphabet, but Str::upper then folds it onto 36 symbols
 * NON-UNIFORMLY: every letter absorbs its lowercase twin, so a letter is twice as
 * likely as a digit. That is ~5.12 bits per character, ~30.7 bits over six — about
 * 1.7e9, not the `62^6 ≈ 5.7e10` claimed in the 2026-09-03 review and in
 * routes/tenant.php. A 33x overstatement of the only thing protecting that page.
 *
 * It was also a latent availability bug. Nothing retries on collision —
 * BookingService generates the reference inside the booking transaction, under the
 * vehicle row lock — so a duplicate surfaces as a QueryException that fails a real
 * customer's booking. At ~30.7 bits the birthday probability reaches ~2.9% by
 * 10,000 bookings in a tenant and ~73% by 50,000.
 *
 * So: draw UNIFORMLY with random_int() from an explicit alphabet, and widen. Do
 * not reach for Str::random plus a transform — uniformity is the entire point, and
 * a transform is exactly how the previous version lost it.
 *
 * The alphabet stays uppercase and drops I, L, O and U (Crockford's set): the
 * reference gets read aloud down a phone line to a rental desk, so 0/O and 1/I
 * ambiguity is a real support cost. 32 symbols over 8 characters is 32^8 ≈ 1.1e12
 * (40 bits) — six orders of magnitude more than before, still short enough to
 * dictate.
 *
 * Widening cannot help references already issued; those links are in customers'
 * inboxes. The `booking-reference-misses` limiter in AppServiceProvider is what
 * defends those.
 */
final class BookingReference
{
    /**
     * Uppercase alphanumerics minus I, L, O and U — removed for read-aloud
     * clarity, not for entropy. Exactly 32 symbols, so each draw is 5 clean bits.
     */
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** 8 symbols over a 32-symbol alphabet = 40 bits. */
    private const int LENGTH = 8;

    public static function generate(): string
    {
        $suffix = '';
        $lastIndex = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            // random_int() is the CSPRNG-backed, modulo-bias-free primitive.
            $suffix .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return 'BK-'.now()->year.'-'.$suffix;
    }
}
