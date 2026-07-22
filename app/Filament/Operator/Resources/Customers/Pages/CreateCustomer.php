<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Customers\Pages;

use App\Filament\Operator\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
