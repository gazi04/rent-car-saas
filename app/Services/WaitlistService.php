<?php

namespace App\Services;

use App\Enums\VehicleStatus;
use App\Mail\VehicleBackInStockMail;
use App\Mail\WaitlistSlotOpenMail;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WaitlistEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Waitlist joins and the "a slot opened" notification sweep (backlog #2), plus
 * the stock alert that shares the table (backlog #3).
 *
 * Shared by the event listeners (immediate, on a booking being cancelled or
 * rejected, or a vehicle becoming bookable) and the nightly sweep command —
 * which is the whole reason this is a service rather than logic in either caller.
 *
 * The two triggers keep separate notify methods on purpose. An entry with dates
 * competes for one scarce range and is resolved FIFO; a dateless stock alert has
 * nothing to claim (a republished vehicle is free for every future date), so
 * everyone waiting is told. Folding the second into notifyMatching() would inherit
 * its claim loop and, because overlaps(null, null) is true, mail exactly one
 * person and dribble the rest out one per nightly sweep.
 */
class WaitlistService
{
    /** Dateless entries never expire on their own; the sweep retires them here. */
    private const STOCK_ALERT_TTL_DAYS = 90;

    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Record someone waiting. tenant_id is supplied by BelongsToTenant.
     *
     * @param  array{name: string, email: string, phone?: string|null, start_date: string, end_date: string, locale?: string|null}  $data
     */
    public function join(Vehicle $vehicle, array $data): WaitlistEntry
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        if (! $start->lt($end)) {
            throw new \InvalidArgumentException('start_date must be before end_date.');
        }

        if ($start->lt(now()->startOfDay())) {
            throw new \InvalidArgumentException('Cannot join a waitlist for dates in the past.');
        }

        return WaitlistEntry::create([
            'vehicle_id' => $vehicle->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'start_date' => $start,
            'end_date' => $end,
            'locale' => $data['locale'] ?? null,
        ]);
    }

    /**
     * Record someone waiting for a whole vehicle rather than a date range (#3).
     *
     * Null dates are the storage shape for "tell me whenever this one is bookable
     * again", so there is no range to validate. A repeat join violates the partial
     * unique index; that bubbles to the caller exactly as join()'s does.
     *
     * @param  array{name: string, email: string, phone?: string|null, locale?: string|null}  $data
     */
    public function joinStockAlert(Vehicle $vehicle, array $data): WaitlistEntry
    {
        return WaitlistEntry::create([
            'vehicle_id' => $vehicle->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'start_date' => null,
            'end_date' => null,
            'locale' => $data['locale'] ?? null,
        ]);
    }

    /**
     * Mail everyone waiting on this vehicle to come back, and return how many were
     * told. Callers must have already checked the plan feature.
     *
     * Everyone, not the first — see the class docblock. The vehicle-level check is
     * the whole test: there is no range, so there is no isAvailable() re-check to
     * make, and it doubles as the guard for the sweep, which calls this for
     * vehicles it hasn't confirmed are back.
     */
    public function notifyStockAlerts(Vehicle $vehicle): int
    {
        if (! $vehicle->is_public || $vehicle->status !== VehicleStatus::Available) {
            return 0;
        }

        $tenant = Tenant::find($vehicle->tenant_id);

        if ($tenant === null) {
            return 0;
        }

        $entries = WaitlistEntry::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('notified_at')
            ->whereNull('start_date')
            ->orderBy('created_at')
            ->get();

        foreach ($entries as $entry) {
            Mail::to($entry->email)
                ->locale($entry->locale ?? $tenant->operatorLocale())
                ->queue(VehicleBackInStockMail::forTenantDomain($entry));

            $entry->update(['notified_at' => now()]);
        }

        return $entries->count();
    }

    /**
     * Mail everyone whose wanted dates are now genuinely free, and return how
     * many were told. Callers must have already checked the plan feature.
     *
     * FIFO does NOT mean "one person". Entries that don't overlap each other
     * aren't competing for the same slot, so they all get told; among entries
     * that DO overlap, only the earliest-joined hears — the rest stay pending and
     * the nightly sweep offers the slot onward if that person never books.
     *
     * The isAvailable() re-check is load-bearing: the freed booking may not have
     * been the only thing covering an entry's range, and a blocked date or a
     * second booking can still make it unbookable.
     *
     * Pass a freed range to narrow the scan to entries that could care; omit it
     * (the sweep) to re-examine every pending entry. CarbonInterface because the
     * range arrives as CarbonImmutable from Booking; it is only compared.
     */
    public function notifyMatching(Vehicle $vehicle, ?CarbonInterface $freedStart = null, ?CarbonInterface $freedEnd = null): int
    {
        $tenant = Tenant::find($vehicle->tenant_id);

        if ($tenant === null) {
            return 0;
        }

        $entries = WaitlistEntry::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('notified_at')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '>=', now()->startOfDay())
            ->orderBy('created_at') // FIFO
            ->get()
            ->filter(fn (WaitlistEntry $entry): bool => $entry->overlaps($freedStart, $freedEnd));

        /** @var array<int, array{0: Carbon, 1: Carbon}> $claimed */
        $claimed = [];
        $notified = 0;

        foreach ($entries as $entry) {
            if ($this->overlapsClaimed($entry, $claimed)) {
                continue;
            }

            // AvailabilityService takes a mutable Carbon, but AppServiceProvider
            // sets Date::use(CarbonImmutable), so every model date cast is
            // immutable. Convert, the way BookingService does by parsing strings.
            $available = $this->availability->isAvailable(
                $vehicle,
                Carbon::instance($entry->start_date),
                Carbon::instance($entry->end_date),
            );

            if (! $available) {
                continue;
            }

            Mail::to($entry->email)
                ->locale($entry->locale ?? $tenant->operatorLocale())
                ->queue(WaitlistSlotOpenMail::forTenantDomain($entry));

            $entry->update(['notified_at' => now()]);

            $claimed[] = [$entry->start_date, $entry->end_date];
            $notified++;
        }

        return $notified;
    }

    /**
     * Entries that can no longer serve anyone are dead weight; the sweep clears them.
     *
     * Two shapes go stale differently. A dated entry dies the day its range starts.
     * A dateless one has no such date and would otherwise sit forever waiting on a
     * vehicle that may never come back — and nobody wants a mail about a car they
     * asked after a year ago — so it ages out on a TTL instead.
     */
    public function purgeExpired(): int
    {
        return WaitlistEntry::query()
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $dated): void {
                        $dated
                            ->whereNotNull('start_date')
                            ->whereDate('start_date', '<', now()->startOfDay());
                    })
                    ->orWhere(function (Builder $dateless): void {
                        $dateless
                            ->whereNull('start_date')
                            ->where('created_at', '<', now()->subDays(self::STOCK_ALERT_TTL_DAYS));
                    });
            })
            ->delete();
    }

    /**
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $claimed
     */
    private function overlapsClaimed(WaitlistEntry $entry, array $claimed): bool
    {
        foreach ($claimed as [$start, $end]) {
            if ($entry->overlaps($start, $end)) {
                return true;
            }
        }

        return false;
    }
}
