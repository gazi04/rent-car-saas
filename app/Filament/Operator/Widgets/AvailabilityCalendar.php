<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Filament\Support\HelpAction;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AvailabilityCalendar extends FullCalendarWidget
{
    public Model|string|null $model = BlockedDate::class;

    // Custom view: wraps the plugin's calendar with a vehicle filter and a
    // status-color legend (the stock plugin view has neither).
    protected string $view = 'filament.operator.widgets.availability-calendar';

    /** @var int|string|null Vehicle ID filter (empty string/null = all vehicles). */
    public int|string|null $vehicleFilter = null;

    public function updatedVehicleFilter(): void
    {
        $this->refreshRecords();
    }

    /**
     * Blocking/unblocking vehicle dates is fleet management — owner-only, matching
     * VehicleResource::canAccess(). Staff keep the read-only calendar view.
     */
    private function isOwner(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    /**
     * @return array<int|string, string> Vehicle options for the filter select.
     */
    public function vehicleOptions(): array
    {
        return Vehicle::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Weeks start on Monday for the Kosovo market. headerToolbar adds day/week/list
     * view buttons (the dayGrid/timeGrid/list FullCalendar plugins are already loaded
     * by default, so these view names need no extra plugin registration); dayMaxEvents
     * caps a busy day behind a "+N more" popover instead of the month grid growing
     * unbounded; nowIndicator draws the current-time line (useful once week view exists).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,listWeek',
            ],
            'dayMaxEvents' => true,
            'nowIndicator' => true,
        ];
    }

    /** Tags blocked-date events with a CSS class for the diagonal-hatch styling (see the view). */
    public function eventClassNames(): string
    {
        return <<<'JS'
            (arg) => arg.event.extendedProps.type === 'block' ? ['fc-event-blocked'] : []
        JS;
    }

    /**
     * Sets a native title attribute from the event's own title, so hovering a
     * truncated event (month view routinely clips long titles) shows the full
     * "Vehicle · Customer" / "Blocked · reason" text as a plain browser tooltip.
     */
    public function eventDidMount(): string
    {
        return <<<'JS'
            (arg) => { arg.el.setAttribute('title', arg.event.title); }
        JS;
    }

    /**
     * Called by FullCalendar when the user navigates (prev/next/view switch).
     * Returns bookings (color-coded by status, clickable through to the booking
     * page) + blocked dates for this tenant.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(array $info): array
    {
        $start = $info['start'];
        $end = $info['end'];

        $bookingQuery = Booking::query()
            ->with('vehicle')
            ->whereIn('status', BookingStatus::blocking())
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start);

        $blockedQuery = BlockedDate::query()
            ->with('vehicle')
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start);

        if ($this->vehicleFilter !== null) {
            $bookingQuery->where('vehicle_id', $this->vehicleFilter);
            $blockedQuery->where('vehicle_id', $this->vehicleFilter);
        }

        $events = [];

        foreach ($bookingQuery->get() as $booking) {
            $events[] = EventData::make()
                ->id('booking-'.$booking->id)
                ->title(($booking->vehicle->name ?? 'Vehicle').' · '.$booking->customer_name)
                ->start($booking->start_date)
                ->end($booking->end_date)
                ->url(BookingResource::getUrl('view', ['record' => $booking]))
                ->backgroundColor($booking->status->calendarColor())
                ->textColor('#ffffff')
                ->extendedProps(['type' => 'booking'])
                ->toArray();
        }

        foreach ($blockedQuery->get() as $block) {
            $events[] = EventData::make()
                ->id('block-'.$block->id)
                ->title(__('panel.legend_blocked').(filled($block->reason) ? ' · '.$block->reason : ''))
                ->start($block->start_date)
                ->end($block->end_date)
                ->backgroundColor('#9ca3af')
                ->textColor('#ffffff')
                ->extendedProps(['type' => 'block', 'block_id' => $block->id])
                ->toArray();
        }

        return $events;
    }

    /**
     * When the user selects a date range, open the create-block modal.
     *
     * @param  array<string, mixed>|null  $view
     * @param  array<string, mixed>|null  $resource
     */
    public function onDateSelect(string $start, ?string $end, bool $allDay, ?array $view, ?array $resource): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $this->mountAction('create', [
            'type' => 'select',
            'start' => $start,
            'end' => $end,
            'allDay' => $allDay,
        ]);
    }

    /**
     * Blocked-date events open a confirm-removal modal (never delete on a bare
     * click — a misclick must not silently drop a block). Booking events carry
     * their own URL and navigate to the booking page without reaching here.
     *
     * @param  array<string, mixed>  $event
     */
    public function onEventClick(array $event): void
    {
        if (($event['extendedProps']['type'] ?? null) !== 'block') {
            return;
        }

        if (! $this->isOwner()) {
            return;
        }

        $blockId = $event['extendedProps']['block_id'] ?? null;

        if (blank($blockId)) {
            return;
        }

        $this->mountAction('removeBlock', ['block_id' => $blockId]);
    }

    public function removeBlockAction(): Action
    {
        return Action::make('removeBlock')
            ->visible(fn (): bool => $this->isOwner())
            ->label(__('panel.remove_block'))
            ->modalHeading(__('panel.remove_block'))
            ->modalDescription(function (array $arguments): string {
                $block = BlockedDate::query()->with('vehicle')->whereKey($arguments['block_id'] ?? null)->first();

                return $block !== null
                    ? ($block->vehicle->name ?? '').' · '
                        .$block->start_date->toDateString().' → '.$block->end_date->toDateString()
                        .(filled($block->reason) ? ' · '.$block->reason : '')
                    : '';
            })
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                BlockedDate::destroy($arguments['block_id'] ?? null);

                Notification::make()->title(__('panel.date_block_removed'))->success()->send();

                $this->refreshRecords();
            });
    }

    /** @return array<int, Component> */
    public function getFormSchema(): array
    {
        return [
            Select::make('vehicle_id')
                ->label(__('panel.vehicle'))
                ->options(Vehicle::query()->pluck('name', 'id'))
                ->required()
                ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Vehicle::query()->whereKey($value)->exists()) {
                        $fail(__('panel.invalid_vehicle'));
                    }
                }),
            DatePicker::make('start_date')
                ->label(__('panel.from'))
                ->required(),
            DatePicker::make('end_date')
                ->label(__('panel.until'))
                ->required()
                ->after('start_date')
                // Reject a block that overlaps an existing occupying booking
                // (Pending/Confirmed/Active) — the operator would otherwise
                // block a car that's already rented in that range.
                ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $vehicleId = $get('vehicle_id');
                    $start = $get('start_date');

                    if (blank($vehicleId) || blank($start) || blank($value)) {
                        return;
                    }

                    $startDate = Date::parse($start);
                    $endDate = Date::parse($value);

                    if (! $startDate->lt($endDate)) {
                        return; // inverted/equal handled by ->after('start_date')
                    }

                    $vehicle = Vehicle::query()->whereKey($vehicleId)->first();

                    if ($vehicle === null) {
                        return; // missing/cross-tenant handled by the vehicle_id rule
                    }

                    if (resolve(AvailabilityService::class)->hasBookingConflict($vehicle, $startDate, $endDate)) {
                        $fail(__('panel.block_overlaps_booking'));
                    }
                }),
            TextInput::make('reason')
                ->label(__('panel.reason'))
                ->placeholder(__('panel.reason_placeholder'))
                ->maxLength(100),
        ];
    }

    /** @return array<int, Action> */
    protected function headerActions(): array
    {
        return [
            HelpAction::make('availability_calendar'),
            CreateAction::make()
                ->visible(fn (): bool => $this->isOwner())
                ->label(__('panel.block_dates'))
                // Pre-fill the pickers from a calendar drag-select; the header
                // button opens them empty and the operator picks the range.
                ->mountUsing(function (Schema $schema, array $arguments): void {
                    $schema->fill([
                        'start_date' => $arguments['start'] ?? null,
                        'end_date' => $arguments['end'] ?? null,
                        'vehicle_id' => $this->vehicleFilter,
                    ]);
                })
                ->using(fn (array $data, string $model): BlockedDate => $model::create([
                    'vehicle_id' => $data['vehicle_id'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'reason' => $data['reason'] ?? null,
                ]))
                ->after(fn () => $this->refreshRecords()),
        ];
    }

    /** @return array<int, Action> */
    protected function modalActions(): array
    {
        return [];
    }
}
