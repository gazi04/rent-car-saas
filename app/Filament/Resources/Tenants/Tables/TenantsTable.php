<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Enums\PaymentMethod;
use App\Enums\TenantStatus;
use App\Filament\Resources\Tenants\Pages\ViewTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use STS\FilamentImpersonate\Actions\Impersonate;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Tenant $record): string => ViewTenant::getUrl(['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('domains.domain')
                    ->label('Subdomain')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('plan')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('trial_ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Not enrolled'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TenantStatus::class),
            ])
            ->recordActions([
                self::impersonateAction(),
                self::approveAction(),
                self::recordPaymentAction(),
                self::extendPeriodAction(),
                self::changePlanAction(),
                self::extendTrialAction(),
                self::suspendAction(),
                self::reactivateAction(),
                self::rejectAction(),
                self::purgeAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Log in as this tenant's operator ("impersonate") to reproduce a bug or
     * verify their branding/fleet without their password. The package swaps the
     * shared `web` session to the operator user and redirects to their dashboard
     * on the tenant subdomain (session survives via SESSION_DOMAIN). The action
     * auto-hides when the tenant has no operator user yet (blank target), and
     * User::canImpersonate() restricts the whole thing to Super Admins.
     */
    protected static function impersonateAction(): Impersonate
    {
        return Impersonate::make()
            ->label('Log in as operator')
            ->icon('heroicon-o-finger-print')
            ->color('warning')
            ->impersonateRecord(fn (Tenant $record): ?User => self::operatorFor($record))
            ->redirectTo(fn (Tenant $record): string => $record->publicRootUrl().'/dashboard')
            ->withoutSpa()
            // Impersonation swaps the shared session cookie and redirects from the
            // admin host to the tenant subdomain — impossible unless the cookie is
            // scoped to a shared parent domain. When SESSION_DOMAIN is unset
            // (host-only, e.g. local .localhost dev) the swap would strand the admin
            // logged in as the operator with no way back, so hide the action. It
            // reappears in production where SESSION_DOMAIN=.yourdomain.com. The
            // second clause overrides the package's default target-present check.
            ->visible(fn (Tenant $record): bool => filled(config('session.domain'))
                && self::operatorFor($record) instanceof User);
    }

    /** The tenant's owner (operator) account — the impersonation target. */
    protected static function operatorFor(Tenant $record): ?User
    {
        return User::query()
            ->where('tenant_id', $record->id)
            ->where('role', 'operator')
            ->first();
    }

    /**
     * Approve a pending operator (pending -> active) and start its free trial:
     * paid_until = now() + billing.trial_days puts the tenant into the daily
     * subscription sweep (reminders -> grace -> suspend). An existing paid_until
     * is never overwritten (re-approving must not reset a paid period).
     */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === TenantStatus::Pending)
            ->action(function (Tenant $record): void {
                $record->update([
                    'status' => TenantStatus::Active,
                    'paid_until' => $record->paid_until
                        ?? now()->addDays((int) config('billing.trial_days'))->endOfDay(),
                ]);

                self::logAdminAction($record, 'approved');
            });
    }

    /**
     * Write an admin audit entry (§15.5) for an action performed on a tenant:
     * who did it (causer = current admin), to which tenant (subject), and any
     * contextual note. Null property values are dropped so blank notes/fields
     * don't clutter the log.
     *
     * @param  array<string, mixed>  $properties
     */
    protected static function logAdminAction(Tenant $tenant, string $description, array $properties = []): void
    {
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties(array_filter($properties, fn (mixed $value): bool => $value !== null))
            ->log($description);
    }

    /**
     * Record a manually received B2B payment (cash / bank transfer — no gateway).
     *
     * Advances paid_until by the covered period, updates the plan, and reactivates the
     * tenant if it was suspended. On-time payments stack onto the existing paid_until
     * (paying early never costs the operator days); late payments start from today.
     */
    protected static function recordPaymentAction(): Action
    {
        return Action::make('record_payment')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->visible(fn (Tenant $record): bool => in_array($record->status, [TenantStatus::Active, TenantStatus::Suspended], true))
            ->schema([
                Select::make('plan')
                    ->options(fn (Tenant $record): array => Plan::options($record->plan))
                    ->default(fn (Tenant $record): ?string => $record->plan)
                    ->required(),
                Select::make('method')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::BankTransfer->value)
                    ->required(),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->required(),
                DatePicker::make('period_start')
                    ->label('Period start')
                    ->default(fn (Tenant $record): CarbonInterface => self::nextPeriodStart($record))
                    ->required(),
                DatePicker::make('period_end')
                    ->label('Period end')
                    ->default(fn (Tenant $record): CarbonInterface => self::nextPeriodStart($record)->addMonthNoOverflow())
                    ->after('period_start')
                    ->required(),
                Textarea::make('note')
                    ->placeholder('e.g. bank transfer ref. #1234')
                    ->maxLength(500),
            ])
            ->action(function (Tenant $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $record->payments()->create([
                        'plan' => $data['plan'],
                        'method' => $data['method'],
                        'amount' => $data['amount'],
                        'period_start' => $data['period_start'],
                        'period_end' => $data['period_end'],
                        'note' => $data['note'] ?? null,
                        'recorded_by' => auth()->id(),
                    ]);

                    $record->update([
                        'plan' => $data['plan'],
                        'paid_until' => Date::parse($data['period_end'])->endOfDay(),
                        'status' => $record->status === TenantStatus::Suspended ? TenantStatus::Active : $record->status,
                    ]);

                    self::logAdminAction($record, 'recorded_payment', [
                        'plan' => $data['plan'],
                        'amount' => $data['amount'],
                        'method' => $data['method'],
                        'note' => $data['note'] ?? null,
                    ]);
                });
            });
    }

    /**
     * Where the next paid period begins: the current paid_until when the tenant is
     * paid up (stacking — paying early keeps the remaining days), today when the
     * previous period already lapsed or none exists yet.
     */
    protected static function nextPeriodStart(Tenant $record): CarbonInterface
    {
        $paidUntil = $record->paid_until;

        return ($paidUntil !== null && $paidUntil->isFuture())
            ? $paidUntil->copy()
            : now();
    }

    /**
     * Comp time / correction: adjust paid_until without a payment row. Either add
     * N days (stacking onto the current paid_until, same rule as record_payment)
     * or set an explicit date. A suspended tenant paid into the future is flipped
     * back to active — a paid-up tenant shouldn't stay suspended.
     */
    protected static function extendPeriodAction(): Action
    {
        return Action::make('extend_period')
            ->label('Extend period')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->visible(fn (Tenant $record): bool => in_array($record->status, [TenantStatus::Active, TenantStatus::Suspended], true))
            ->schema([
                Radio::make('mode')
                    ->options([
                        'add_days' => 'Add days',
                        'set_date' => 'Set exact date',
                    ])
                    ->default('add_days')
                    ->live()
                    ->required(),
                TextInput::make('days')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('mode') === 'add_days'),
                DatePicker::make('paid_until')
                    ->label('Paid until')
                    ->default(fn (Tenant $record): CarbonInterface => self::nextPeriodStart($record))
                    ->required()
                    ->visible(fn (Get $get): bool => $get('mode') === 'set_date'),
                Textarea::make('note')
                    ->placeholder('Reason for this adjustment')
                    ->maxLength(500),
            ])
            ->action(function (Tenant $record, array $data): void {
                $newPaidUntil = $data['mode'] === 'set_date'
                    ? Date::parse($data['paid_until'])->endOfDay()
                    : self::nextPeriodStart($record)->addDays((int) $data['days'])->endOfDay();

                $record->update([
                    'paid_until' => $newPaidUntil,
                    'status' => $record->status === TenantStatus::Suspended ? TenantStatus::Active : $record->status,
                ]);

                self::logAdminAction($record, 'extended_period', [
                    'paid_until' => $newPaidUntil->toDateString(),
                    'mode' => $data['mode'],
                    'days' => $data['days'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            });
    }

    /**
     * Switch a tenant's plan mid-cycle with no payment involved. Tenant::allowsFeature()
     * keys off `plan`, so this changes enabled features immediately — hence the
     * confirmation warning.
     */
    protected static function changePlanAction(): Action
    {
        return Action::make('change_plan')
            ->label('Change plan')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (Tenant $record): bool => in_array($record->status, [TenantStatus::Active, TenantStatus::Suspended], true))
            ->requiresConfirmation()
            ->modalDescription("This changes the tenant's enabled features immediately, with no payment recorded.")
            ->schema([
                Select::make('plan')
                    ->options(fn (Tenant $record): array => Plan::options($record->plan))
                    ->default(fn (Tenant $record): ?string => $record->plan)
                    ->required(),
                Textarea::make('note')
                    ->placeholder('Reason for this change')
                    ->maxLength(500),
            ])
            ->action(function (Tenant $record, array $data): void {
                $from = $record->getOriginal('plan');

                $record->update(['plan' => $data['plan']]);

                self::logAdminAction($record, 'changed_plan', [
                    'from' => $from,
                    'to' => $data['plan'],
                    'note' => $data['note'] ?? null,
                ]);
            });
    }

    /**
     * Push a trial out by N days. Bumps both trial_ends_at and paid_until — during
     * the trial, paid_until tracks the trial end (set at approval), and the daily
     * subscription sweep (reminders -> grace -> suspend) keys off paid_until, so
     * both must move together.
     */
    protected static function extendTrialAction(): Action
    {
        return Action::make('extend_trial')
            ->label('Extend trial')
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->visible(fn (Tenant $record): bool => $record->status === TenantStatus::Active && $record->isOnTrial())
            ->schema([
                TextInput::make('days')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Textarea::make('note')
                    ->placeholder('Reason for this extension')
                    ->maxLength(500),
            ])
            ->action(function (Tenant $record, array $data): void {
                $days = (int) $data['days'];

                $record->update([
                    'trial_ends_at' => ($record->trial_ends_at ?? now())->addDays($days),
                    'paid_until' => ($record->paid_until ?? now())->addDays($days)->endOfDay(),
                ]);

                self::logAdminAction($record, 'extended_trial', [
                    'days' => $days,
                    'note' => $data['note'] ?? null,
                ]);
            });
    }

    protected static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === TenantStatus::Active)
            ->action(function (Tenant $record): void {
                $record->update(['status' => TenantStatus::Suspended]);

                self::logAdminAction($record, 'suspended');
            });
    }

    protected static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === TenantStatus::Suspended)
            ->action(function (Tenant $record): void {
                $record->update(['status' => TenantStatus::Active]);

                self::logAdminAction($record, 'reactivated');
            });
    }

    /**
     * Reject an operator (cancel them).
     */
    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => in_array($record->status, [TenantStatus::Pending, TenantStatus::Active, TenantStatus::Suspended], true))
            ->action(function (Tenant $record): void {
                $record->update(['status' => TenantStatus::Cancelled]);

                self::logAdminAction($record, 'rejected');
            });
    }

    /**
     * Permanently delete an abandoned signup, freeing its subdomain.
     *
     * domains.domain is globally unique, so a never-approved signup holds its
     * subdomain forever — rejecting only flips the status. This is the only way
     * to release one. Deliberately manual: deleting cascades to the tenant's
     * domains, users and data, so an admin decides case by case rather than a
     * sweep doing it unattended. Restricted to signups that never became a real
     * business — pending/cancelled, past the abandonment window, no bookings.
     */
    protected static function purgeAction(): Action
    {
        return Action::make('purge')
            ->label('Purge')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Purge abandoned signup')
            ->modalDescription(function (Tenant $record): string {
                $domains = $record->domains->pluck('domain')->implode(', ');

                return sprintf(
                    'Permanently deletes %s and frees the subdomain %s for re-registration. Its users and settings go with it. This cannot be undone.',
                    $record->name,
                    $domains === '' ? '—' : $domains,
                );
            })
            ->modalSubmitActionLabel('Purge permanently')
            ->visible(fn (Tenant $record): bool => self::isAbandoned($record))
            ->action(function (Tenant $record): void {
                // Log before the delete: activity() needs the subject row to exist.
                self::logAdminAction($record, 'purged', [
                    'domains' => $record->domains->pluck('domain')->all(),
                    'status' => $record->status->value,
                ]);

                $record->delete();
            });
    }

    /**
     * A signup that never became a business: pending or cancelled, older than
     * the abandonment window, and with no bookings to preserve.
     */
    protected static function isAbandoned(Tenant $record): bool
    {
        if (! in_array($record->status, [TenantStatus::Pending, TenantStatus::Cancelled], true)) {
            return false;
        }

        if ($record->created_at->isAfter(now()->subDays(Config::integer('tenancy.abandoned_after_days')))) {
            return false;
        }

        return $record->bookings->isEmpty();
    }
}
