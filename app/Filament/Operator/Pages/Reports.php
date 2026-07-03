<?php

namespace App\Filament\Operator\Pages;

use App\Enums\BookingStatus;
use App\Enums\PlanFeature;
use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only aggregation over the tenant's existing bookings — counts, revenue,
 * and per-vehicle utilisation for a chosen date range, with CSV export. All
 * queries are auto tenant-scoped via BelongsToTenant.
 *
 * @property-read Schema $form
 */
class Reports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.operator.pages.reports';

    /** Plan-gated: hides the nav item and blocks the route when the plan disables reports. */
    public static function canAccess(): bool
    {
        return tenant()?->allowsFeature(PlanFeature::Reports) ?? true;
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
            ->with(['bookings' => fn ($query) => $query
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

    public function exportCsv(): StreamedResponse
    {
        [$rangeStart, $rangeEnd] = $this->range();
        $filename = sprintf('bookings-%s-%s.csv', $rangeStart->toDateString(), $rangeEnd->toDateString());

        $bookings = $this->bookingsInRange()->with('vehicle')->orderBy('start_date')->get();

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
            ($start ? Carbon::parse($start) : now()->startOfMonth())->startOfDay(),
            ($end ? Carbon::parse($end) : now()->endOfMonth())->endOfDay(),
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
