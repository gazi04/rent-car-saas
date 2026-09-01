<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Bounds two operational tables that otherwise grow without a ceiling
| (deep-audit 2026-08-25, finding 10): the admin audit trail and failed queue
| jobs. Both are pruned on the daily schedule (routes/console.php); these tests
| drive the underlying commands directly, the same way EmailDeliveryLogTest
| covers the email-log prune.
*/

// ── activity_log ─────────────────────────────────────────────────────────────

it('prunes audit-log rows past the retention window and keeps the rest', function () {
    config(['activitylog.clean_after_days' => 365]);

    $stale = insertActivityRow(now()->subDays(400));
    $edge = insertActivityRow(now()->subDays(364));
    $fresh = insertActivityRow(now());

    $this->artisan('activitylog:clean')->assertSuccessful();

    expect(activityRowExists($stale))->toBeFalse()
        ->and(activityRowExists($edge))->toBeTrue()
        ->and(activityRowExists($fresh))->toBeTrue();
});

it('honours a changed audit-log retention window', function () {
    config(['activitylog.clean_after_days' => 7]);

    $row = insertActivityRow(now()->subDays(30));

    $this->artisan('activitylog:clean')->assertSuccessful();

    expect(activityRowExists($row))->toBeFalse();
});

// ── failed_jobs ──────────────────────────────────────────────────────────────

it('prunes failed jobs past the retention window and keeps the rest', function () {
    $stale = insertFailedJob(now()->subDays(40));
    $fresh = insertFailedJob(now()->subDays(5));

    // 720 hours = the 30-day window scheduled in routes/console.php.
    $this->artisan('queue:prune-failed', ['--hours' => 720])->assertSuccessful();

    expect(failedJobExists($stale))->toBeFalse()
        ->and(failedJobExists($fresh))->toBeTrue();
});

// ── helpers ──────────────────────────────────────────────────────────────────

function insertActivityRow(DateTimeInterface $createdAt): int
{
    return DB::table('activity_log')->insertGetId([
        'log_name' => 'default',
        'description' => 'probe',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function activityRowExists(int $id): bool
{
    return DB::table('activity_log')->where('id', $id)->exists();
}

function insertFailedJob(DateTimeInterface $failedAt): string
{
    $uuid = Str::uuid()->toString();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'probe',
        'failed_at' => $failedAt,
    ]);

    return $uuid;
}

function failedJobExists(string $uuid): bool
{
    return DB::table('failed_jobs')->where('uuid', $uuid)->exists();
}
