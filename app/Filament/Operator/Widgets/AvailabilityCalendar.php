<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;
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
     * @return array<int|string, string> Vehicle options for the filter select.
     */
    public function vehicleOptions(): array
    {
        return Vehicle::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Weeks start on Monday for the Kosovo market.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'firstDay' => 1,
        ];
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

        if ($this->vehicleFilter) {
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
                ->backgroundColor(match ($booking->status) {
                    BookingStatus::Pending => '#f59e0b',
                    BookingStatus::Confirmed => '#3b82f6',
                    BookingStatus::Active => '#22c55e',
                    default => '#6b7280',
                })
                ->textColor('#ffffff')
                ->extendedProps(['type' => 'booking'])
                ->toArray();
        }

        foreach ($blockedQuery->get() as $block) {
            $events[] = EventData::make()
                ->id('block-'.$block->id)
                ->title(__('panel.legend_blocked').($block->reason ? ' · '.$block->reason : ''))
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

        $blockId = $event['extendedProps']['block_id'] ?? null;

        if (! $blockId) {
            return;
        }

        $this->mountAction('removeBlock', ['block_id' => $blockId]);
    }

    public function removeBlockAction(): Action
    {
        return Action::make('removeBlock')
            ->label(__('panel.remove_block'))
            ->modalHeading(__('panel.remove_block'))
            ->modalDescription(function (array $arguments): string {
                $block = BlockedDate::query()->with('vehicle')->whereKey($arguments['block_id'] ?? null)->first();

                return $block
                    ? ($block->vehicle->name ?? '').' · '
                        .$block->start_date->toDateString().' → '.$block->end_date->toDateString()
                        .($block->reason ? ' · '.$block->reason : '')
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
                ->required(),
            DatePicker::make('start_date')
                ->label(__('panel.from'))
                ->required(),
            DatePicker::make('end_date')
                ->label(__('panel.until'))
                ->required()
                ->after('start_date'),
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
            Actions\CreateAction::make()
                ->label(__('panel.block_dates'))
                // Pre-fill the pickers from a calendar drag-select; the header
                // button opens them empty and the operator picks the range.
                ->mountUsing(function (Schema $schema, array $arguments): void {
                    $schema->fill([
                        'start_date' => $arguments['start'] ?? null,
                        'end_date' => $arguments['end'] ?? null,
                        'vehicle_id' => $this->vehicleFilter ?: null,
                    ]);
                })
                ->using(function (array $data, string $model): BlockedDate {
                    return $model::create([
                        'vehicle_id' => $data['vehicle_id'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'reason' => $data['reason'] ?? null,
                    ]);
                })
                ->after(fn () => $this->refreshRecords()),
        ];
    }

    /** @return array<int, Action> */
    protected function modalActions(): array
    {
        return [];
    }
}
