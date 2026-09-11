<?php

use App\Console\Commands\ExpireStalePendingBookings;
use App\Console\Commands\GenerateBusinessSummaries;
use App\Console\Commands\ProcessTenantSubscriptions;
use App\Console\Commands\ProcessVehicleMaintenance;
use App\Console\Commands\PurgeAbandonedTenants;
use App\Console\Commands\RequestPendingReviews;
use App\Console\Commands\SweepWaitlist;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Schedule;

// Manual B2B billing sweep: renewal reminders + auto-suspend on lapse
// .
Schedule::command(ProcessTenantSubscriptions::class)->daily()->withoutOverlapping();

// Weekly AI business summary fan-out: Mondays 06:00,
// one queued job per eligible tenant.
Schedule::command(GenerateBusinessSummaries::class)->weeklyOn(1, '06:00')->withoutOverlapping();

// Daily vehicle maintenance sweep: reminders + auto-block,
// one queued job per eligible tenant.
Schedule::command(ProcessVehicleMaintenance::class)->dailyAt('07:00')->withoutOverlapping();

// Daily waitlist sweep: offer freed dates to the next person in line,
// one queued job per eligible tenant.
Schedule::command(SweepWaitlist::class)->dailyAt('08:00')->withoutOverlapping();

// Daily review-request fan-out: next-day invitation email,
// one queued job per eligible tenant.
Schedule::command(RequestPendingReviews::class)->dailyAt('09:00')->withoutOverlapping();

// Hourly pending-booking expiry sweep: cancel bookings left unconfirmed past
// the window so they stop blocking their vehicle. Hourly, not daily, because
// the window (bookings.pending_expiry_hours) is measured in hours.
Schedule::command(ExpireStalePendingBookings::class)->hourly()->withoutOverlapping();

// Age out the email delivery log (mail.log_retention_days). It grows with send
// volume and is an operational trail, not a business record.
Schedule::command('model:prune', ['--model' => [EmailLog::class]])->dailyAt('04:00');

// Age out the admin audit trail (activitylog.clean_after_days, 365d). Low write
// volume — admin actions only — but an append-only table with no ceiling over
// years. Uses the config window; no --days needed.
Schedule::command('activitylog:clean')->dailyAt('04:15');

// Age out failed queue jobs older than 30 days. Near-empty in healthy operation,
// but every scheduled command is a per-tenant fan-out with $tries = 3, so one
// systemic outage (AI/mail provider down) writes ~one row per tenant per run.
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('04:30');

// Release subdomains held by dead signups. Daily at 04:45, with the other
// retention sweeps: the window is measured in days (tenancy.abandoned_after_days),
// so the run only needs to catch each tenant once per day, and doing it before
// business hours keeps a destructive sweep away from live admin work.
Schedule::command(PurgeAbandonedTenants::class)->dailyAt('04:45')->withoutOverlapping();

// Age out Pulse's rolling window. Same reasoning as the email log above:
// diagnostics, not a business record — and the pulse_* tables grow on every
// request, so this is the one that would grow fastest if left unswept.
Schedule::command('pulse:trim')->hourly();
