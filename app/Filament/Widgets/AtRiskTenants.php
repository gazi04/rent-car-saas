<?php

namespace App\Filament\Widgets;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active tenants whose paid period needs attention: it ends within the grace
 * window from now, OR has already lapsed but they aren't suspended yet. One
 * predicate (paid_until <= now + grace_days) covers both the "reminder due
 * soon" and "in grace" cases the daily sweep (ProcessTenantSubscriptions) acts
 * on. Suspended tenants are excluded — they've already been actioned.
 */
class AtRiskTenants extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('At-risk subscriptions')
            ->emptyStateHeading('No subscriptions need attention')
            ->query($this->atRiskQuery())
            ->defaultSort('paid_until', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('plan')
                    ->badge(),
                TextColumn::make('paid_until')
                    ->label('Paid until')
                    ->date()
                    ->description(fn (Tenant $record): string => $record->paid_until?->diffForHumans() ?? ''),
                TextColumn::make('state')
                    ->badge()
                    ->state(fn (Tenant $record): string => $record->paid_until !== null && $record->paid_until->isPast() ? 'Grace' : 'Due soon')
                    ->color(fn (string $state): string => $state === 'Grace' ? 'danger' : 'warning'),
            ]);
    }

    /** @return Builder<Tenant> */
    protected function atRiskQuery(): Builder
    {
        $boundary = now()->addDays((int) config('billing.grace_days'));

        return Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->whereNotNull('paid_until')
            ->where('paid_until', '<=', $boundary);
    }
}
