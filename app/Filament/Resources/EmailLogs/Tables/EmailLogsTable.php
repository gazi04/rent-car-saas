<?php

namespace App\Filament\Resources\EmailLogs\Tables;

use App\Enums\EmailStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('Platform')
                    ->searchable(),
                TextColumn::make('to_email')
                    ->label('To')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('mailable')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EmailStatus $state): string => $state->color()),
                TextColumn::make('error')
                    ->label('Reason')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(EmailStatus::cases())->mapWithKeys(fn (EmailStatus $status): array => [$status->value => $status->getLabel()])->all()),
                SelectFilter::make('tenant')
                    ->relationship('tenant', 'name'),
            ]);
    }
}
