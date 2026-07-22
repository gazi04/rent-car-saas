<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Stancl\Tenancy\Database\Models\Domain;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                // The operator's subdomain. On create this becomes a row in the
                // tenant's domains() relation so it resolves immediately (Step 1).
                TextInput::make('subdomain')
                    ->required()
                    ->rule('regex:/^[a-z0-9]+(-[a-z0-9]+)*$/')
                    ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                        $domain = $value.'.'.config('tenancy.tenant_base_domain', 'localhost');
                        if (Domain::query()->where('domain', $domain)->exists()) {
                            $fail('This subdomain is already taken.');
                        }
                    })
                    ->helperText("Lowercase letters, numbers and hyphens. Becomes the operator's booking site.")
                    ->suffix('.'.config('tenancy.tenant_base_domain', 'localhost'))
                    ->visibleOn('create')
                    ->dehydrated(),
                Select::make('status')
                    ->required()
                    ->default(TenantStatus::Pending)
                    ->options(TenantStatus::class),
                Select::make('plan')
                    ->options(fn (?Tenant $record): array => Plan::options($record?->plan)),
                DateTimePicker::make('trial_ends_at'),
            ]);
    }
}
