<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    /**
     * @param  bool  $includeTenant  Show the tenant column/filter (false inside
     *                               the Tenant relation manager — it's redundant).
     */
    public static function configure(Table $table, bool $includeTenant = true): Table
    {
        $columns = [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('email')
                ->searchable()
                ->copyable(),
            TextColumn::make('role')
                ->badge(),
        ];

        if ($includeTenant) {
            $columns[] = TextColumn::make('tenant.name')
                ->label('Tenant')
                ->searchable()
                ->sortable();
        }

        $columns[] = IconColumn::make('email_verified_at')
            ->label('Verified')
            ->boolean();
        $columns[] = TextColumn::make('created_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        $filters = [
            SelectFilter::make('role')
                ->options([
                    'operator' => 'Operator (owner)',
                    'staff' => 'Staff',
                ]),
            TernaryFilter::make('email_verified_at')
                ->label('Verified')
                ->nullable(),
        ];

        if ($includeTenant) {
            $filters[] = SelectFilter::make('tenant_id')
                ->label('Tenant')
                ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable();
        }

        return $table
            ->columns($columns)
            ->filters($filters)
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                self::verifyEmailAction(),
                self::resetPasswordAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Manually mark an operator/staff email as verified — the direct UI fix for
     * an account that can't reach the operator panel (canAccessPanel() requires
     * a verified email).
     */
    public static function verifyEmailAction(): Action
    {
        return Action::make('verify_email')
            ->label('Verify email')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
            ->action(function (User $record): void {
                $record->forceFill(['email_verified_at' => now()])->save();
                self::logUserAction($record, 'verified_user_email');
                Notification::make()->title('Email verified')->success()->send();
            });
    }

    /** Set a new password for an operator/staff account (cast hashes it). */
    public static function resetPasswordAction(): Action
    {
        return Action::make('reset_password')
            ->label('Reset password')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->schema([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->maxLength(255),
            ])
            ->action(function (User $record, array $data): void {
                $record->forceFill(['password' => $data['password']])->save();
                self::logUserAction($record, 'reset_user_password');
                Notification::make()->title('Password reset')->success()->send();
            });
    }

    /**
     * Does the tenant already have an owner (role = operator)? Used to guard
     * against creating/promoting a second owner. Optionally ignore one user
     * (the record being edited).
     */
    public static function tenantHasOperator(int $tenantId, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'operator')
            ->when($ignoreUserId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreUserId))
            ->exists();
    }

    /**
     * Create an operator/staff user. role/tenant_id aren't mass-assignable and
     * User has no tenant global scope, so force-fill them; created pre-verified
     * (admin-set account — no verification email).
     *
     * @param  array<string, mixed>  $data
     */
    public static function createTenantUser(array $data, int $tenantId): User
    {
        $user = new User;

        $user->forceFill([
            'tenant_id' => $tenantId,
            'role' => $data['role'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ])->save();

        self::logUserAction($user, 'created_user');

        return $user;
    }

    /**
     * Update name/email/role (tenant is fixed) and the password only when a new
     * one was entered.
     *
     * @param  array<string, mixed>  $data
     */
    public static function updateTenantUser(User $record, array $data): User
    {
        $record->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        if (filled($data['password'] ?? null)) {
            $record->forceFill(['password' => $data['password']]);
        }

        $record->save();

        self::logUserAction($record, 'updated_user');

        return $record;
    }

    /**
     * Audit-log a user-management action, mirroring TenantsTable::logAdminAction
     * so the admin ActivityResource trail stays complete.
     *
     * @param  array<string, mixed>  $properties
     */
    protected static function logUserAction(User $user, string $description, array $properties = []): void
    {
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties($properties)
            ->log($description);
    }
}
