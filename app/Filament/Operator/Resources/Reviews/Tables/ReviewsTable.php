<?php

namespace App\Filament\Operator\Resources\Reviews\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        // No tenant filter — the BelongsToTenant global scope already limits
        // rows to the current operator's tenant.
        return $table
            ->columns([
                TextColumn::make('vehicle.name')
                    ->label(__('panel.vehicle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reviewer_name')
                    ->label(__('panel.review_reviewer'))
                    ->searchable(),
                TextColumn::make('rating')
                    ->label(__('panel.review_rating'))
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->sortable(),
                TextColumn::make('comment')
                    ->label(__('panel.review_comment'))
                    ->limit(60)
                    ->tooltip(fn (Review $record): ?string => $record->comment)
                    ->placeholder('—'),
                IconColumn::make('is_approved')
                    ->label(__('panel.review_approved'))
                    ->boolean(),
                TextColumn::make('submitted_at')
                    ->label(__('panel.review_submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label(__('panel.review_approved')),
                SelectFilter::make('vehicle_id')
                    ->relationship('vehicle', 'name')
                    ->label(__('panel.vehicle')),
            ])
            ->recordActions([
                self::approveAction(),
                self::hideAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Unapproved → approved: the review starts rendering on the public site. */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('panel.review_action_approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Review $record): bool => ! $record->is_approved)
            ->action(fn (Review $record): bool => $record->update(['is_approved' => true]));
    }

    /** Approved → hidden: pull the review from the public site without deleting it. */
    protected static function hideAction(): Action
    {
        return Action::make('hide')
            ->label(__('panel.review_action_hide'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Review $record): bool => $record->is_approved)
            ->action(fn (Review $record): bool => $record->update(['is_approved' => false]));
    }
}
