<?php

namespace App\Filament\Operator\Resources\Staff\Pages;

use App\Filament\Operator\Resources\Staff\StaffResource;
use App\Filament\Support\PlanLimit;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * Hide "Create & create another" once the next save would fill the plan's
     * last seat, so the owner can't chain past the cap onto a blank form with
     * no feedback — plain "Create" then redirects to the (bannered) list.
     */
    public function canCreateAnother(): bool
    {
        return PlanLimit::staffCreateAnotherAllowed();
    }

    /**
     * Seat-cap backstop: block a new staff account once the tenant is at its
     * plan's cap, even if the disabled button was bypassed. The always-visible
     * banner (OperatorPanelProvider) is the primary signal.
     */
    protected function beforeCreate(): void
    {
        if (PlanLimit::staffSeatsReached()) {
            Notification::make()
                ->title(__('panel.staff_seat_limit_reached_title'))
                ->body(__('panel.staff_seat_limit_reached_body', ['limit' => PlanLimit::staffSeatLimit()]))
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
