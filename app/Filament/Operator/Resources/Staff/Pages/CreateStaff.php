<?php

namespace App\Filament\Operator\Resources\Staff\Pages;

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\Staff\StaffResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * Seat-cap backstop: block a new staff account once the tenant is at its
     * plan's cap, even if the disabled button was bypassed.
     */
    protected function beforeCreate(): void
    {
        $limit = Tenant::current()?->featureLimit(PlanFeature::StaffSeatLimit);

        if ($limit !== null && User::query()->where('tenant_id', tenant('id'))->where('role', 'staff')->count() >= $limit) {
            Notification::make()
                ->title(__('panel.staff_seat_limit_reached_title'))
                ->body(__('panel.staff_seat_limit_reached_body', ['limit' => $limit]))
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * User is not tenant-scoped and role/tenant_id aren't mass-assignable, so
     * force-fill the tenant + staff role. The 'hashed' cast hashes the password.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;

        $user->forceFill([
            'tenant_id' => tenant('id'),
            'role' => 'staff',
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
