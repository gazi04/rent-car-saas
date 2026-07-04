<?php

namespace App\Jobs;

use App\Enums\VehicleStatus;
use App\Mail\ServiceDueMail;
use App\Models\BlockedDate;
use App\Models\ServiceRecord;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Processes one tenant's due service records (operator feature #10): sends a
 * reminder once per record as its due date approaches, then auto-blocks the
 * vehicle (via the existing blocked_dates mechanism) once it is actually due.
 *
 * Dispatched from the central maintenance:process-due command, so the
 * QueueTenancyBootstrapper does NOT re-initialize tenancy for us — the job
 * initializes (and always ends) tenancy itself.
 */
class ProcessVehicleMaintenanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenant);

        try {
            $owners = User::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('role', 'operator')
                ->get();

            ServiceRecord::query()
                ->whereNotNull('next_due_on')
                ->each(function (ServiceRecord $record) use ($owners): void {
                    if ($record->next_due_on->isPast() || $record->next_due_on->isToday()) {
                        $this->autoBlock($record, $owners);

                        return;
                    }

                    $this->remindIfDue($record, $owners);
                });
        } finally {
            tenancy()->end();
        }
    }

    /** @param  Collection<int, User>  $owners */
    private function remindIfDue(ServiceRecord $record, Collection $owners): void
    {
        if ($record->reminder_sent_at !== null) {
            return;
        }

        /** @var array<int, int> $reminderDays */
        $reminderDays = config('maintenance.reminder_days');
        $isDue = collect($reminderDays)->contains(fn (int $days): bool => $record->next_due_on->isSameDay(now()->addDays($days)));

        if (! $isDue) {
            return;
        }

        foreach ($owners as $owner) {
            Mail::to($owner->email)
                ->locale($this->tenant->operatorLocale())
                ->queue(new ServiceDueMail($record));

            Notification::make()
                ->title(__('panel.service_due_bell_title'))
                ->body($record->vehicle->name)
                ->warning()
                ->sendToDatabase($owner);
        }

        $record->update(['reminder_sent_at' => now()]);
    }

    /** @param  Collection<int, User>  $owners */
    private function autoBlock(ServiceRecord $record, Collection $owners): void
    {
        if ($record->blocked_date_id !== null) {
            return;
        }

        $blockDays = (int) config('maintenance.block_days');

        $blockedDate = BlockedDate::create([
            'vehicle_id' => $record->vehicle_id,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addDays($blockDays)->startOfDay(),
            'reason' => 'maintenance',
        ]);

        $record->update(['blocked_date_id' => $blockedDate->id]);
        $record->vehicle->update(['status' => VehicleStatus::UnderMaintenance]);

        foreach ($owners as $owner) {
            Notification::make()
                ->title(__('panel.service_overdue_bell_title'))
                ->body($record->vehicle->name)
                ->danger()
                ->sendToDatabase($owner);
        }
    }
}
