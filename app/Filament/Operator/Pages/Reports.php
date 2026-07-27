<?php

namespace App\Filament\Operator\Pages;

use App\Enums\BookingStatus;
use App\Enums\PlanFeature;
use App\Filament\Support\HelpAction;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only aggregation over the tenant's existing bookings — counts, revenue,
 * and per-vehicle utilisation for a chosen date range, with CSV export. All
 * queries are auto tenant-scoped via BelongsToTenant.
 *
 * @phpstan-type HeatmapPayload array{
 *     truncated: bool,
 *     fleet_size: int<0, max>,
 *     days: list<array{date: string, day: string, dow: string, is_weekend: bool}>,
 *     rows: list<array{
 *         vehicle: string,
 *         occupied_days: int<0, max>,
 *         cells: list<array{kind: 'free'|'booking'|'block', color: string|null, label: string|null}>,
 *     }>,
 *     demand: list<array{date: string, occupied: int<0, max>, percent: int<0, 100>}>,
 * }
 *
 * @property-read Schema $form
 */
class Reports extends Page
{
    /**
     * Column cap for the occupancy heatmap. The page defaults to the current
     * month (~28-31 days), so the cap is invisible in normal use; beyond it a
     * day-column grid stops being readable and the payload grows with
     * days x fleet size. Long ranges are answered by utilisation() above.
     */
    public const int MAX_HEATMAP_DAYS = 31;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.operator.pages.reports';

    /**
     * Memoized heatmap payload. Deliberately private: a public property would be
     * serialized into every Livewire request payload, and this is a days x fleet
     * matrix.
     *
     * @var HeatmapPayload|null
     */
    private ?array $heatmapCache = null;

    /**
     * Owner-only + plan-gated: hidden and 404 for staff accounts, and when the
     * plan disables reports.
     */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (Tenant::current()?->allowsFeature(PlanFeature::Reports) ?? (bool) PlanFeature::Reports->default());
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                DatePicker::make('start_date')
                    ->label(__('reports.start_date'))
                    ->live()
                    ->required(),
                DatePicker::make('end_date')
                    ->label(__('reports.end_date'))
                    ->live()
                    ->required()
                    ->afterOrEqual('start_date'),
            ])
            ->columns(2);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('reports'),
            Action::make('export_csv')
                ->label(__('reports.export_csv'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /** @return array<string, int> Booking count per status label, plus the total. */
    public function bookingCounts(): array
    {
        $counts = $this->bookingsInRange()
            ->get()
            ->countBy(fn (Booking $booking): string => $booking->status->value)
            ->all();

        return ['total' => array_sum($counts)] + $counts;
    }

    public function revenue(): float
    {
        return (float) $this->bookingsInRange()
            ->whereIn('status', [BookingStatus::Active, BookingStatus::Completed])
            ->sum('total');
    }

    /**
     * Booked days ÷ range days per vehicle, as a rounded percentage. Booked days
     * come from blocking-status bookings, clamped to the selected range.
     *
     * @return Collection<int, array{vehicle: string, booked_days: int<0, max>, percent: int}>
     */
    public function utilisation(): Collection
    {
        [$rangeStart, $rangeEnd] = $this->range();
        $rangeDays = max(1, (int) $rangeStart->diffInDays($rangeEnd->copy()->addDay()->startOfDay()));

        return Vehicle::query()
            ->with(['bookings' => fn (Relation $query) => $query
                ->whereIn('status', BookingStatus::blocking())
                ->where('start_date', '<=', $rangeEnd)
                ->where('end_date', '>=', $rangeStart)])
            ->get()
            ->map(function (Vehicle $vehicle) use ($rangeStart, $rangeEnd, $rangeDays): array {
                $bookedDays = $vehicle->bookings->sum(function (Booking $booking) use ($rangeStart, $rangeEnd): int {
                    $from = $booking->start_date->greaterThan($rangeStart) ? $booking->start_date : $rangeStart;
                    $to = $booking->end_date->lessThan($rangeEnd) ? $booking->end_date : $rangeEnd;

                    return max(0, (int) ceil($from->diffInHours($to) / 24));
                });

                $bookedDays = min($bookedDays, $rangeDays);

                return [
                    'vehicle' => $vehicle->name,
                    'booked_days' => $bookedDays,
                    'percent' => (int) round($bookedDays / $rangeDays * 100),
                ];
            })
            ->sortByDesc('percent')
            ->values();
    }

    /** Semantic color for a utilisation percentage, so idle vehicles stand out. */
    public function utilisationColor(int $percent): string
    {
        return match (true) {
            $percent < 30 => 'danger',
            $percent < 70 => 'warning',
            default => 'success',
        };
    }

    /**
     * Whether this tenant's plan includes the heatmap. Separate from the page's
     * own Reports gate — a plan can have Reports without the heatmap. The
     * heatmap lives on this page, so it is unreachable when Reports is off,
     * whatever this returns (the plan-editor label says so).
     */
    public function showsHeatmap(): bool
    {
        return Tenant::current()?->allowsFeature(PlanFeature::FleetHeatmap)
            ?? (bool) PlanFeature::FleetHeatmap->default();
    }

    /**
     * Per-vehicle x calendar-day occupancy grid for the selected range, plus the
     * fleet-wide demand aggregate per day.
     *
     * Two deliberate divergences from utilisation() above — both disclosed to the
     * operator in help.reports.body:
     *
     * 1. Day math is CALENDAR DAYS TOUCHED (09:00 Mon -> 09:00 Wed occupies three
     *    day cells), where utilisation() measures rental DURATION via ceil(hours/24)
     *    (48h = 2 days). Both are correct for their own question.
     * 2. Completed bookings are included, or every past range would render blank —
     *    utilisation() counts only BookingStatus::blocking().
     *
     * Every date crossing to the view is a bare Y-m-d, never an ISO-8601 instant:
     * a cell is a calendar day, and a "...Z" datetime lets the browser re-anchor it
     * to the viewer's local day and shift the whole grid a column west of UTC. Same
     * rule as the public availability endpoint (routes/tenant.php).
     *
     * @return HeatmapPayload
     */
    public function heatmap(): array
    {
        if ($this->heatmapCache !== null) {
            return $this->heatmapCache;
        }

        [$rangeStart, $rangeEnd] = $this->range();

        $days = $this->heatmapDays($rangeStart, $rangeEnd);

        // Guard before any query: a long range can't render as day columns.
        if (count($days) > self::MAX_HEATMAP_DAYS) {
            return $this->heatmapCache = [
                'truncated' => true,
                'fleet_size' => 0,
                'days' => [],
                'rows' => [],
                'demand' => [],
            ];
        }

        /** @var array<string, int> $dayIndex Y-m-d => column position. */
        $dayIndex = array_flip(array_column($days, 'date'));
        $columns = count($days);

        $vehicles = Vehicle::query()
            ->with([
                'bookings' => fn (Relation $query) => $query
                    ->where('status', '!=', BookingStatus::Cancelled)
                    ->where('start_date', '<=', $rangeEnd)
                    ->where('end_date', '>=', $rangeStart),
                'blockedDates' => fn (Relation $query) => $query
                    ->where('start_date', '<=', $rangeEnd)
                    ->where('end_date', '>=', $rangeStart),
            ])
            ->get();

        $rows = [];

        foreach ($vehicles as $vehicle) {
            /** @var list<array{kind: 'free'|'booking'|'block', color: string|null, label: string|null}> $cells */
            $cells = array_fill(0, $columns, ['kind' => 'free', 'color' => null, 'label' => null]);
            $rank = array_fill(0, $columns, -1);

            // Blocks first, then bookings — a later write wins, so a booking
            // naturally takes precedence over a block on the same day without
            // an explicit branch. Committed revenue outranks a self-made note.
            foreach ($vehicle->blockedDates as $block) {
                foreach ($this->touchedColumns($block->start_date, $block->end_date, $rangeStart, $rangeEnd, $dayIndex) as $i) {
                    $cells[$i] = [
                        'kind' => 'block',
                        'color' => '#9ca3af',
                        'label' => __('panel.legend_blocked').($block->reason !== null ? ' — '.$block->reason : ''),
                    ];
                    $rank[$i] = 0;
                }
            }

            foreach ($vehicle->bookings as $booking) {
                $bookingRank = $this->bookingRank($booking->status);

                foreach ($this->touchedColumns($booking->start_date, $booking->end_date, $rangeStart, $rangeEnd, $dayIndex) as $i) {
                    // Same-day turnover: the most-committed booking owns the cell.
                    if ($rank[$i] >= $bookingRank && $cells[$i]['kind'] === 'booking') {
                        continue;
                    }

                    $cells[$i] = [
                        'kind' => 'booking',
                        'color' => $booking->status->calendarColor(),
                        'label' => sprintf('%s — %s', $booking->reference, $booking->status->getLabel()),
                    ];
                    $rank[$i] = $bookingRank;
                }
            }

            $rows[] = [
                'vehicle' => $vehicle->name,
                'occupied_days' => count(array_filter($cells, fn (array $cell): bool => $cell['kind'] !== 'free')),
                'cells' => $cells,
            ];
        }

        $fleetSize = $vehicles->count();

        // Demand is the column-wise reduction of the grid above: a single
        // vehicle-day is binary, but "how much of the fleet is out" has real
        // magnitude, so this is the only layer where intensity means anything.
        $demand = [];

        foreach ($days as $i => $day) {
            $occupied = count(array_filter(
                $rows,
                fn (array $row): bool => $row['cells'][$i]['kind'] !== 'free',
            ));

            $demand[] = [
                'date' => $day['date'],
                'occupied' => $occupied,
                // Clamped so the 0..100 bound holds and fleet_size = 0 is safe.
                'percent' => $fleetSize > 0
                    ? max(0, min(100, (int) round($occupied / $fleetSize * 100)))
                    : 0,
            ];
        }

        return $this->heatmapCache = [
            'truncated' => false,
            'fleet_size' => $fleetSize,
            'days' => $days,
            'rows' => $rows,
            'demand' => $demand,
        ];
    }

    /** Intensity bucket for a fleet-demand cell. Mirrors utilisationColor()'s thresholds. */
    public function demandClass(int $percent): string
    {
        return match (true) {
            $percent === 0 => 'bg-gray-100 dark:bg-white/5',
            $percent < 30 => 'bg-success-200 dark:bg-success-500/30',
            $percent < 70 => 'bg-warning-300 dark:bg-warning-500/40',
            default => 'bg-danger-400 dark:bg-danger-500/60',
        };
    }

    /**
     * The range's calendar days as bare Y-m-d column descriptors.
     *
     * @return list<array{date: string, day: string, dow: string, is_weekend: bool}>
     */
    protected function heatmapDays(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): array
    {
        $days = [];
        $cursor = CarbonImmutable::parse($rangeStart->toDateString());
        $last = $rangeEnd->toDateString();

        // Cap the walk itself: a multi-year range would otherwise build a huge
        // array only to be thrown away by the caller's truncation guard.
        while ($cursor->toDateString() <= $last && count($days) <= self::MAX_HEATMAP_DAYS) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => $cursor->format('j'),
                'dow' => $cursor->isoFormat('dd'),
                'is_weekend' => $cursor->isWeekend(),
            ];
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Column positions an interval touches, clamped to the range. Day bucketing
     * happens here in PHP (UTC) on Y-m-d strings — never in the browser.
     *
     * Inclusive on both ends, so a booking returning at 00:00 still marks that
     * day. That one-day overcount is deliberate: it matches the public
     * availability endpoint, which blocks the same day for customers.
     *
     * @param  array<string, int>  $dayIndex
     * @return list<int>
     */
    protected function touchedColumns(
        CarbonInterface $start,
        CarbonInterface $end,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        array $dayIndex,
    ): array {
        $from = $start->greaterThan($rangeStart) ? $start : $rangeStart;
        $to = $end->lessThan($rangeEnd) ? $end : $rangeEnd;

        if ($from->greaterThan($to)) {
            return [];
        }

        $columns = [];
        $cursor = $from->toDateString();
        $last = $to->toDateString();

        // Y-m-d is lexicographically ordered, so string comparison is safe here.
        while ($cursor <= $last) {
            if (isset($dayIndex[$cursor])) {
                $columns[] = $dayIndex[$cursor];
            }

            $cursor = CarbonImmutable::parse($cursor)->addDay()->toDateString();
        }

        return $columns;
    }

    /**
     * How committed a booking is, for deciding which one owns a shared day cell.
     * Local to this page on purpose — the enum is a shared contract and this
     * ordering is only meaningful to the heatmap.
     */
    protected function bookingRank(BookingStatus $status): int
    {
        return match ($status) {
            BookingStatus::Active => 3,
            BookingStatus::Confirmed => 2,
            BookingStatus::Pending => 1,
            default => 0,
        };
    }

    public function exportCsv(): StreamedResponse
    {
        [$rangeStart, $rangeEnd] = $this->range();
        $filename = sprintf('bookings-%s-%s.csv', $rangeStart->toDateString(), $rangeEnd->toDateString());

        $bookings = $this->bookingsInRange()->with('vehicle')->oldest('start_date')->get();

        return response()->streamDownload(function () use ($bookings): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['reference', 'customer', 'vehicle', 'start', 'end', 'status', 'total']);

            foreach ($bookings as $booking) {
                fputcsv($out, [
                    $booking->reference,
                    $booking->customer_name,
                    $booking->vehicle?->name,
                    $booking->start_date->toDateTimeString(),
                    $booking->end_date->toDateTimeString(),
                    $booking->status->value,
                    $booking->total,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return Builder<Booking> Bookings overlapping the selected range (tenant-scoped). */
    protected function bookingsInRange(): Builder
    {
        [$rangeStart, $rangeEnd] = $this->range();

        return Booking::query()
            ->where('start_date', '<=', $rangeEnd)
            ->where('end_date', '>=', $rangeStart);
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    protected function range(): array
    {
        $start = $this->data['start_date'] ?? null;
        $end = $this->data['end_date'] ?? null;

        return [
            ($start !== null && $start !== '' ? Date::parse($start) : now()->startOfMonth())->startOfDay(),
            ($end !== null && $end !== '' ? Date::parse($end) : now()->endOfMonth())->endOfDay(),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('reports.navigation_label');
    }

    public function getTitle(): string
    {
        return __('reports.navigation_label');
    }
}
