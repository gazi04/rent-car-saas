<?php

namespace App\Filament\Operator\Resources\Customers;

use App\Filament\Operator\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Operator\Resources\Customers\Pages\EditCustomer;
use App\Filament\Operator\Resources\Customers\Pages\ListCustomers;
use App\Filament\Operator\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Operator\Resources\Customers\RelationManagers\BookingsRelationManager;
use App\Filament\Operator\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Operator\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    // Sits after Vehicles/Bookings and before Reports (9) / Branding (10).
    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_customers');
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
