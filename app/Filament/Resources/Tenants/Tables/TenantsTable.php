<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Enums\PaymentMethod;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use STS\FilamentImpersonate\Actions\Impersonate;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
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
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                self::impersonateAction(),
                self::approveAction(),
                self::recordPaymentAction(),
                self::suspendAction(),
                self::reactivateAction(),
                self::rejectAction(),
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
                && self::operatorFor($record) !== null);
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
     *
     * TODO (Notifications step): send "operator approved" email.
     */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === 'pending')
            ->action(fn (Tenant $record) => $record->update([
                'status' => 'active',
                'paid_until' => $record->paid_until
                    ?? now()->addDays((int) config('billing.trial_days'))->endOfDay(),
            ]));
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
            ->visible(fn (Tenant $record): bool => in_array($record->status, ['active', 'suspended'], true))
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
                        'paid_until' => Carbon::parse($data['period_end'])->endOfDay(),
                        'status' => $record->status === 'suspended' ? 'active' : $record->status,
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

    protected static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === 'active')
            ->action(fn (Tenant $record) => $record->update(['status' => 'suspended']));
    }

    protected static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === 'suspended')
            ->action(fn (Tenant $record) => $record->update(['status' => 'active']));
    }

    /**
     * Reject an operator (cancel them).
     *
     * TODO (Notifications step): send "operator rejected" email.
     */
    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => in_array($record->status, ['pending', 'active', 'suspended'], true))
            ->action(fn (Tenant $record) => $record->update(['status' => 'cancelled']));
    }
}
