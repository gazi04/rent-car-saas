<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\BookingStatus;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Reactive;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AvailabilityCalendar extends FullCalendarWidget
{
    public Model|string|null $model = BlockedDate::class;

    /** @var int|string|null Vehicle ID filter (null = all vehicles). */
    #[Reactive]
    public int|string|null $vehicleFilter = null;

    /**
     * Called by FullCalendar when the user navigates (prev/next/view switch).
     * Returns bookings (color-coded by status) + blocked dates for this tenant.
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
                ->backgroundColor(match ($booking->status) {
                    BookingStatus::Pending => '#f59e0b',
                    BookingStatus::Confirmed => '#3b82f6',
                    BookingStatus::Active => '#22c55e',
                    default => '#6b7280',
                })
                ->extendedProps(['type' => 'booking'])
                ->toArray();
        }

        foreach ($blockedQuery->get() as $block) {
            $events[] = EventData::make()
                ->id('block-'.$block->id)
                ->title('Blocked'.($block->reason ? ' · '.$block->reason : ''))
                ->start($block->start_date)
                ->end($block->end_date)
                ->backgroundColor('#9ca3af')
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
     * When the user clicks an event, delete it if it's a blocked date.
     * Booking events are read-only on this calendar.
     *
     * @param  array<string, mixed>  $event
     */
    public function onEventClick(array $event): void
    {
        $type = $event['extendedProps']['type'] ?? null;

        if ($type !== 'block') {
            return;
        }

        $blockId = $event['extendedProps']['block_id'] ?? null;

        if (! $blockId) {
            return;
        }

        BlockedDate::destroy($blockId);

        Notification::make()->title('Date block removed')->success()->send();

        $this->refreshRecords();
    }

    /** @return array<int, Component> */
    public function getFormSchema(): array
    {
        return [
            Select::make('vehicle_id')
                ->label('Vehicle')
                ->options(Vehicle::query()->pluck('name', 'id'))
                ->required(),
            TextInput::make('reason')
                ->label('Reason')
                ->placeholder('maintenance, personal, other…')
                ->maxLength(100),
        ];
    }

    /** @return array<int, Action> */
    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Block dates')
                ->using(function (array $data, string $model): BlockedDate {
                    return $model::create([
                        'vehicle_id' => $data['vehicle_id'],
                        'start_date' => $data['start'] ?? now(),
                        'end_date' => $data['end'] ?? now()->addDay(),
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
