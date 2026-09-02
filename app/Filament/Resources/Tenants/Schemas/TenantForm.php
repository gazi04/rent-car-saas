<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Rules\AvailableSubdomain;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                //
                // AvailableSubdomain is shared with operator self-signup on purpose:
                // this form used to carry its own copy of the format and "already
                // taken" checks and no reserved-name check at all, so an admin could
                // hand an operator a platform hostname like "admin".
                TextInput::make('subdomain')
                    ->required()
                    ->rule(new AvailableSubdomain)
                    ->helperText("Lowercase letters, numbers and hyphens. Becomes the operator's booking site.")
                    ->suffix('.'.config()->string('tenancy.tenant_base_domain', 'localhost'))
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
