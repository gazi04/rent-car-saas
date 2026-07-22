<?php

namespace App\Filament\Operator\Resources\ServiceRecords\Tables;

use App\Models\ServiceRecord;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceRecordsTable
{
    /** The furthest-out reminder day defines the "due soon" badge window. */
    private static function dueSoonThreshold(): CarbonImmutable
    {
        /** @var array<int, int> $reminderDays */
        $reminderDays = config('maintenance.reminder_days');

        return now()->addDays(collect($reminderDays)->max());
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.name')
                    ->label(__('panel.vehicle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service_type')
                    ->label(__('panel.service_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('panel.service_type_'.$state)),
                TextColumn::make('performed_on')
                    ->label(__('panel.service_performed_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('next_due_on')
                    ->label(__('panel.service_next_due_on'))
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    ->badge()
                    ->color(fn (?ServiceRecord $record): ?string => match (true) {
                        $record?->next_due_on === null => null,
                        $record->next_due_on->isPast() => 'danger',
                        $record->next_due_on->lte(self::dueSoonThreshold()) => 'warning',
                        default => null,
                    })
                    ->formatStateUsing(function (?ServiceRecord $record): string {
                        if ($record?->next_due_on === null) {
                            return '—';
                        }

                        $label = $record->next_due_on->toFormattedDateString();

                        return match (true) {
                            $record->next_due_on->isPast() => $label.' — '.__('panel.service_overdue'),
                            $record->next_due_on->lte(self::dueSoonThreshold()) => $label.' — '.__('panel.service_due'),
                            default => $label,
                        };
                    }),
                TextColumn::make('cost')
                    ->label(__('panel.service_cost'))
                    ->money('EUR')
                    ->placeholder('—'),
            ])
            ->defaultSort('performed_on', 'desc')
            ->filters([
                SelectFilter::make('vehicle_id')
                    ->label(__('panel.vehicle'))
                    ->relationship('vehicle', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
