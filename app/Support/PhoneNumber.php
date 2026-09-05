<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical form of a phone number for *identity* purposes — the string the
 * customers.[tenant_id, phone] unique key is matched on.
 *
 * Punctuation only: digits are kept, plus at most one leading "+", and
 * everything else (spaces, non-breaking spaces, dashes, dots, slashes,
 * parentheses) is dropped. Without this, "+383 44 123 456" and "+38344123456"
 * were two different customers, which meant a promo code's per_customer_limit
 * could be spent again by re-typing the same number with a space in it.
 *
 * Country codes are deliberately NOT folded — "044123456", "0038344123456" and
 * "+38344123456" stay distinct. Collapsing them needs an assumption about the
 * caller's dialing context, and getting that assumption wrong merges two real
 * people's records irreversibly. Punctuation carries no such risk: no two
 * humans are distinguished by whether a space was typed.
 */
class PhoneNumber
{
    /**
     * Accepts mixed on purpose: every caller sits on a trust boundary and holds
     * an untyped value — a raw key out of a request payload, a Livewire public
     * property. The contract is "give me whatever arrived, get a canonical
     * string back", so the coercion belongs here rather than repeated at each
     * call site.
     */
    public static function normalize(mixed $phone): string
    {
        $phone = is_string($phone) || is_int($phone) ? trim((string) $phone) : '';

        $leadingPlus = str_starts_with($phone, '+') ? '+' : '';

        return $leadingPlus.preg_replace('/\D+/', '', $phone);
    }
}
