<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * The single validator for an operator subdomain: format, reserved names, and
 * availability.
 *
 * There are two places a subdomain can be claimed — operator self-signup
 * (pages/auth/operator-register) and the admin TenantForm — and they had drifted:
 * the admin form carried the regex and the "already taken" check but no reserved
 * list at all, so an admin could hand an operator "admin.<domain>". Both now go
 * through here.
 *
 * The availability check is advisory, not authoritative. It is a read followed by
 * an insert, so two concurrent signups can both pass it; the `domains.domain`
 * unique index is what actually arbitrates, and the callers catch its violation.
 * This rule exists to produce a friendly field error in the overwhelmingly common
 * case, not to guarantee exclusivity.
 */
class AvailableSubdomain implements ValidationRule
{
    /**
     * A DNS label: lowercase alphanumerics in hyphen-separated groups. Rejects
     * uppercase, leading/trailing hyphens, and anything punycode-shaped.
     */
    private const string FORMAT = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::FORMAT, $value) !== 1) {
            $fail(__('Use lowercase letters, numbers and hyphens only.'));

            return;
        }

        if (strlen($value) > 63) {
            $fail(__('A subdomain may be at most 63 characters.'));

            return;
        }

        if (in_array($value, self::reserved(), strict: true)) {
            $fail(__('This subdomain is reserved.'));

            return;
        }

        if (Domain::query()->where('domain', self::fullDomain($value))->exists()) {
            $fail(__('This subdomain is already taken.'));
        }
    }

    /**
     * Expand a subdomain into the host stored in the `domains` table.
     */
    public static function fullDomain(string $subdomain): string
    {
        return $subdomain.'.'.config()->string('tenancy.tenant_base_domain', 'localhost');
    }

    /**
     * @return list<string>
     */
    private static function reserved(): array
    {
        /** @var list<string> $reserved */
        $reserved = config()->array('tenancy.reserved_subdomains', []);

        return $reserved;
    }
}
