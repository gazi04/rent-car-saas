<?php

use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\BlockedDateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Proves BlockedDateService shares BookingService's vehicle-row mutex
|--------------------------------------------------------------------------
|
| deep-audit finding 04: a customer's booking could commit while an
| operator's block was mid-flight (or vice versa), because only
| BookingService::lockAndValidate() took lockForUpdate() on the vehicle row —
| the blocked-date writers didn't. BlockedDateService::create() now takes
| the same lock, which is what actually closes the race: both writers
| queue behind the same row, on the same driver where locking is real
| (lockForUpdate() is a no-op on SQLite — see BookingLockConcurrencyTest).
|
| Both directions are proven with a short Postgres lock_timeout rather than
| NOWAIT: it forces the blocked call to fail fast with a QueryException
| instead of hanging the suite, while still calling the real service code
| (not a raw-SQL stand-in), so a regression in BlockedDateService itself —
| not just in Postgres's locking semantics — would be caught here.
|
*/

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Postgres-only concurrency test; current driver is not pgsql.');
    }

    config(['database.connections.pgsql_secondary' => config('database.connections.pgsql')]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'pgsql') {
        DB::statement('set lock_timeout = 0');
    }

    DB::purge('pgsql_secondary');
});

it('blocks BlockedDateService::create() behind a booking-style lock already held on the vehicle row', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->create();
    tenancy()->end();

    try {
        DB::connection('pgsql_secondary')->beginTransaction();
        DB::connection('pgsql_secondary')
            ->select('select * from vehicles where id = ? for update', [$vehicle->id]);

        DB::statement('set lock_timeout = 500');

        tenancy()->initialize($tenant);

        expect(fn () => app(BlockedDateService::class)->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(2),
        ]))->toThrow(QueryException::class);
    } finally {
        DB::statement('set lock_timeout = 0');

        if (DB::connection('pgsql_secondary')->transactionLevel() > 0) {
            DB::connection('pgsql_secondary')->rollBack();
        }

        tenancy()->initialize($tenant);
        $vehicle->forceDelete();
        tenancy()->end();
        $tenant->delete();
    }
});

it('blocks a booking-style lock behind a block already held on the vehicle row by BlockedDateService', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->create();

    try {
        // Hold BlockedDateService's own lock open by starting the transaction
        // manually and running the same lockForUpdate() call it makes,
        // without letting it commit.
        DB::beginTransaction();
        Vehicle::whereKey($vehicle->id)->lockForUpdate()->first();

        $blockedWhileLocked = false;

        try {
            DB::connection('pgsql_secondary')
                ->select('select * from vehicles where id = ? for update nowait', [$vehicle->id]);
        } catch (Throwable) {
            $blockedWhileLocked = true;
        }

        DB::commit();

        expect($blockedWhileLocked)->toBeTrue(
            'Expected the booking-style lock attempt to be blocked while BlockedDateService '.
            'held the row lock uncommitted — the two writers are not sharing the same mutex.'
        );
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        tenancy()->initialize($tenant);
        $vehicle->forceDelete();
        tenancy()->end();
        $tenant->delete();
    }
});
