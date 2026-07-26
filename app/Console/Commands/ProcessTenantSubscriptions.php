<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantSubscriptionSuspended;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Daily sweep of the manual B2B billing cycle:
 * reminds operators before their paid period (trial or paid — same field) ends,
 * and auto-suspends tenants whose period lapsed past the grace window, alerting
 * the admins. Thresholds live in config/billing.php.
 *
 * Tenants with paid_until = null are not enrolled in the cycle and are skipped.
 * Matching is by calendar date, so the daily run catches each tenant exactly once
 * per threshold regardless of the time-of-day stored in paid_until.
 */
#[Signature('tenants:process-subscriptions')]
#[Description('Send subscription renewal reminders and suspend tenants whose paid period lapsed')]
class ProcessTenantSubscriptions extends Command
{
    public function handle(): int
    {
        $processed = 0;

        /** @var int $graceDays */
        $graceDays = config('billing.grace_days');

        // cursor() keeps the Tenant generic; ->get() would return stancl's
        // non-generic TenantCollection and drop the model type.
        $tenants = Tenant::query()
            ->whereNotNull('paid_until')
            ->where('status', TenantStatus::Active->value)
            ->cursor();

        foreach ($tenants as $tenant) {
            if ($tenant->paid_until !== null && $tenant->paid_until->addDays($graceDays)->isPast()) {
                $this->suspend($tenant);
            } else {
                $this->remindIfDue($tenant);
            }

            $processed++;
        }

        $this->info(sprintf('Processed %d enrolled tenants.', $processed));

        return self::SUCCESS;
    }

    private function remindIfDue(Tenant $tenant): void
    {
        if ($tenant->email === null) {
            return;
        }

        /** @var array<int, int> $reminderDays */
        $reminderDays = config('billing.reminder_days');

        foreach ($reminderDays as $days) {
            if ($tenant->paid_until !== null && $tenant->paid_until->isSameDay(now()->addDays($days))) {
                Mail::to($tenant->email)
                    ->locale($tenant->operatorLocale())
                    ->queue(new SubscriptionRenewalReminderMail($tenant, $days));

                return;
            }
        }
    }

    private function suspend(Tenant $tenant): void
    {
        $tenant->update(['status' => TenantStatus::Suspended]);

        $admins = User::query()
            ->where('role', 'admin')
            ->whereNull('tenant_id')
            ->get();

        Notification::send($admins, new TenantSubscriptionSuspended($tenant));

        foreach ($admins as $admin) {
            FilamentNotification::make()
                ->title('Tenant suspended — subscription lapsed')
                ->body($tenant->name.' · paid until '.$tenant->paid_until?->toFormattedDateString())
                ->danger()
                ->sendToDatabase($admin);
        }

        $this->warn(sprintf('Suspended %s (paid until %s).', $tenant->name, $tenant->paid_until?->toDateString()));
    }
}
