<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

afterEach(fn () => tenancy()->end());

/*
|--------------------------------------------------------------------------
| The phone-normalization backfill migration
|--------------------------------------------------------------------------
|
| Normalizing on write only fixes rows written from now on. Rows already in
| customers keep their punctuation, which means an existing customer stays
| split across two records and the promo per_customer_limit stays spendable
| twice for them specifically. The migration collapses them.
|
| Merging customer records is destructive, so the parts that must not go wrong
| are pinned here: the OLDEST row survives (it owns the history the operator has
| been building), the loser's bookings are repointed rather than orphaned, and a
| field only present on the loser is carried over rather than lost.
|
| The migration is invoked directly rather than re-running `migrate`: the test
| database is already migrated, so re-running it would be a no-op against a
| schema that has no duplicates left to merge.
|
*/

function runPhoneBackfill(): void
{
    (require dirname(__DIR__, 3).'/database/migrations/2026_09_04_120000_normalize_customer_phone_numbers.php')->up();
}

it('merges punctuation duplicates into the oldest record and repoints its bookings', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $vehicle = Vehicle::factory()->create();

    // Pre-migration shape: two rows for one human. Written through the query
    // builder because the model now normalizes on write, which is the very thing
    // that makes this state unreachable going forward.
    $keeperId = DB::table('customers')->insertGetId([
        'tenant_id' => $tenant->id, 'name' => 'Arben', 'phone' => '+383 44 000 000',
        'email' => null, 'notes' => 'VIP', 'is_blacklisted' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $loserId = DB::table('customers')->insertGetId([
        'tenant_id' => $tenant->id, 'name' => 'Arben K', 'phone' => '+38344000000',
        'email' => 'arben@example.test', 'notes' => null, 'is_blacklisted' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $booking = Booking::factory()->create(['vehicle_id' => $vehicle->id, 'customer_id' => $loserId]);

    runPhoneBackfill();

    expect(Customer::query()->count())->toBe(1);

    $merged = Customer::query()->sole();

    expect($merged->id)->toBe($keeperId)
        ->and($merged->phone)->toBe('+38344000000')
        ->and($merged->notes)->toBe('VIP')
        // Carried from the loser rather than lost: it held the only email, and a
        // blacklist flag set on either row has to survive the merge.
        ->and($merged->email)->toBe('arben@example.test')
        ->and($merged->is_blacklisted)->toBeTrue()
        ->and($booking->fresh()->customer_id)->toBe($keeperId);

    expect(Customer::query()->whereKey($loserId)->exists())->toBeFalse();
})->group('security');

it('does not merge the same number across two tenants', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    foreach ([$a, $b] as $tenant) {
        DB::table('customers')->insert([
            'tenant_id' => $tenant->id, 'name' => 'Arben', 'phone' => '+383 44 000 000',
            'email' => null, 'notes' => null, 'is_blacklisted' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    runPhoneBackfill();

    // Two operators' directories are separate universes; the same number in each
    // is two different customer relationships.
    expect(DB::table('customers')->count())->toBe(2);
})->group('security');

it('normalizes a lone row without deleting anything', function () {
    $tenant = Tenant::factory()->create();

    DB::table('customers')->insert([
        'tenant_id' => $tenant->id, 'name' => 'Arben', 'phone' => ' (044) 123.456 ',
        'email' => null, 'notes' => null, 'is_blacklisted' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    runPhoneBackfill();

    expect(DB::table('customers')->count())->toBe(1)
        ->and(DB::table('customers')->value('phone'))->toBe('044123456');
})->group('security');
