<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Tenants\Pages\ViewTenant;
use App\Filament\Resources\Tenants\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Tenants\Schemas\TenantForm;
use App\Filament\Resources\Tenants\Tables\TenantsTable;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'tenant';

    public static function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantsTable::configure($table);
    }

    /**
     * The tenant overview page (§15.4) — everything scattered across
     * TenantsTable columns/actions, on one read-only screen. Runs in the
     * admin panel's central context, where tenancy is never initialized, so
     * the BelongsToTenant global scope on Vehicle/Booking is a no-op and a
     * plain where('tenant_id', ...) count is correctly cross-tenant-safe.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity & contact')
                    ->columns(2)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('email')->placeholder('—'),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                Section::make('Subscription')
                    ->columns(2)
                    ->components([
                        TextEntry::make('plan')->badge(),
                        TextEntry::make('subscriptionPlan.price')
                            ->label('Plan price')
                            ->money('EUR')
                            ->placeholder('—'),
                        TextEntry::make('paid_until')->dateTime()->placeholder('Not enrolled'),
                        TextEntry::make('trial_ends_at')->dateTime()->placeholder('—'),
                    ]),
                Section::make('Access')
                    ->columns(2)
                    ->components([
                        TextEntry::make('domains.domain')
                            ->label('Subdomain(s)')
                            ->badge()
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                        TextEntry::make('operators')
                            ->label('Operator / staff')
                            ->state(fn (Tenant $record): string => User::query()
                                ->where('tenant_id', $record->id)
                                ->whereIn('role', ['operator', 'staff'])
                                ->get()
                                ->map(fn (User $user): string => "{$user->name} ({$user->email})")
                                ->implode(', ') ?: '—'),
                    ]),
                Section::make('Usage')
                    ->columns(2)
                    ->components([
                        TextEntry::make('fleet_size')
                            ->label('Fleet size')
                            ->state(fn (Tenant $record): int => Vehicle::where('tenant_id', $record->id)->count()),
                        TextEntry::make('bookings_count')
                            ->label('Bookings')
                            ->state(fn (Tenant $record): int => Booking::where('tenant_id', $record->id)->count()),
                    ]),
                Section::make('Last activity')
                    ->components([
                        TextEntry::make('last_activity')
                            ->label('')
                            ->state(function (Tenant $record): string {
                                $activity = Activity::query()
                                    ->where('subject_type', Tenant::class)
                                    ->where('subject_id', $record->id)
                                    ->latest()
                                    ->first();

                                if ($activity === null) {
                                    return 'No activity';
                                }

                                return "{$activity->description} — {$activity->created_at?->diffForHumans()}";
                            }),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'view' => ViewTenant::route('/{record}'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
