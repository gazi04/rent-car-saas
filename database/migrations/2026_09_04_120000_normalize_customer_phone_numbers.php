<?php

declare(strict_types=1);

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse punctuation variants of the same phone number into one customer.
 *
 * customers.[tenant_id, phone] is the identity key, and it was matched on the
 * raw string — so "+383 44 123 456" and "+38344123456" were two customers, and
 * a promo code's per_customer_limit could be spent again just by re-typing the
 * number with a space in it. App\Support\PhoneNumber::normalize() is now applied
 * on every write; this brings existing rows in line with it.
 *
 * Runs in CENTRAL context: tenancy is never initialized during `migrate`, so the
 * BelongsToTenant global scope does not apply. Everything here goes through the
 * query builder with explicit tenant_id grouping rather than the scoped model.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $customers = DB::table('customers')
                ->select('id', 'tenant_id', 'phone', 'email', 'notes', 'is_blacklisted')
                ->orderBy('id')
                ->get();

            /** @var array<string, list<array{id: int, tenant_id: int, phone: string, email: string|null, notes: string|null, is_blacklisted: bool}>> $groups keyed by "tenant_id|normalized phone" */
            $groups = [];

            foreach ($customers as $row) {
                $customer = $this->toRow($row);

                $groups[$customer['tenant_id'].'|'.PhoneNumber::normalize($customer['phone'])][] = $customer;
            }

            foreach ($groups as $key => $group) {
                // Ordered by id, so the first is the oldest — that record keeps
                // its id, and with it the bookings and whatever the operator has
                // since edited into it.
                $keeper = array_shift($group);

                if ($keeper === null) {
                    continue;
                }

                $keeper = $this->absorbDuplicates($keeper, $group);

                $normalized = explode('|', $key, 2)[1];

                // Renaming the keeper has to come LAST. Doing it first collides
                // with a not-yet-removed duplicate that already holds the
                // normalized string, and customers.[tenant_id, phone] is unique.
                if ($normalized !== $keeper['phone']) {
                    DB::table('customers')->where('id', $keeper['id'])->update(['phone' => $normalized]);
                }
            }
        });
    }

    /**
     * Repoint each duplicate's bookings onto $keeper, carry over anything only
     * the duplicate holds, then delete it. Returns the updated keeper.
     *
     * Coalesce rather than clobber: a duplicate may carry the only email or note
     * anyone ever recorded for this person, and a blacklist flag set on either
     * row has to survive the merge — losing it would silently re-admit someone
     * the operator had blocked.
     *
     * @param  array{id: int, tenant_id: int, phone: string, email: string|null, notes: string|null, is_blacklisted: bool}  $keeper
     * @param  list<array{id: int, tenant_id: int, phone: string, email: string|null, notes: string|null, is_blacklisted: bool}>  $duplicates
     * @return array{id: int, tenant_id: int, phone: string, email: string|null, notes: string|null, is_blacklisted: bool}
     */
    private function absorbDuplicates(array $keeper, array $duplicates): array
    {
        foreach ($duplicates as $duplicate) {
            DB::table('bookings')->where('customer_id', $duplicate['id'])->update(['customer_id' => $keeper['id']]);

            $fill = [];

            if ($keeper['email'] === null && $duplicate['email'] !== null) {
                $fill['email'] = $duplicate['email'];
            }

            if ($keeper['notes'] === null && $duplicate['notes'] !== null) {
                $fill['notes'] = $duplicate['notes'];
            }

            if (! $keeper['is_blacklisted'] && $duplicate['is_blacklisted']) {
                $fill['is_blacklisted'] = true;
            }

            if ($fill !== []) {
                DB::table('customers')->where('id', $keeper['id'])->update($fill);

                $keeper = [...$keeper, ...$fill];
            }

            DB::table('customers')->where('id', $duplicate['id'])->delete();
        }

        return $keeper;
    }

    /**
     * Narrow one query-builder row into a typed shape.
     *
     * DB::table() hands back stdClass with mixed properties, and this file runs
     * under phpstan-strict-rules where mixed cannot simply be cast. Guarding
     * each column is also the honest thing to do here: the migration rewrites
     * and deletes customer records, so a column that is not what it should be
     * must fail into a harmless default rather than a silent coercion.
     *
     * @return array{id: int, tenant_id: int, phone: string, email: string|null, notes: string|null, is_blacklisted: bool}
     */
    private function toRow(stdClass $row): array
    {
        return [
            'id' => is_numeric($row->id) ? (int) $row->id : 0,
            'tenant_id' => is_numeric($row->tenant_id) ? (int) $row->tenant_id : 0,
            'phone' => is_string($row->phone) ? $row->phone : '',
            'email' => is_string($row->email) ? $row->email : null,
            'notes' => is_string($row->notes) ? $row->notes : null,
            'is_blacklisted' => (bool) $row->is_blacklisted,
        ];
    }

    /**
     * Irreversible by design: the punctuation that was stripped is not recorded
     * anywhere, and the merged duplicates no longer exist to be split back out.
     * Rolling this migration back leaves the normalized data in place, which is
     * still valid for the pre-migration code (it matched on the raw string, and
     * a normalized string is a raw string).
     */
    public function down(): void {}
};
