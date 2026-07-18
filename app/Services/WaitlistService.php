<?php

namespace App\Services;

use App\Mail\WaitlistSlotOpenMail;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WaitlistEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Waitlist joins and the "a slot opened" notification sweep (backlog #2).
 *
 * Shared by the event listener (immediate, on a booking being cancelled or
 * rejected) and the nightly sweep command — which is the whole reason this is
 * a service rather than logic in either caller.
 */
class WaitlistService
{
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

    /** A dated entry that can no longer serve anyone dies the day its range starts. */
    public function purgeExpired(): int
    {
        return WaitlistEntry::query()
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<', now()->startOfDay())
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
