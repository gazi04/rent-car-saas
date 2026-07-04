<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Panel access boundaries:
     * - admin:    central Super Admins only (role = admin, no tenant).
     * - operator: the current tenant's owner (role = operator) OR staff
     *             (role = staff), whose tenant_id matches the resolved
     *             subdomain. Blocks cross-tenant login. Per-page/resource
     *             restrictions for staff are handled by canAccess() gates.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'operator') {
            return in_array($this->role, ['operator', 'staff'], true)
                && tenancy()->initialized
                && $this->tenant_id === tenant('id');
        }

        if ($panel->getId() === 'admin') {
            return $this->role === 'admin' && $this->tenant_id === null;
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' && $this->tenant_id === null;
    }

    /**
     * Gate for filament-impersonate: only Super Admins may impersonate an
     * operator (defence in depth — the action already lives on the admin-only
     * panel). Called by the package's Impersonate action.
     */
    public function canImpersonate(): bool
    {
        return $this->isAdmin();
    }

    /** The tenant's owner account (created at registration; full panel access). */
    public function isOwner(): bool
    {
        return $this->role === 'operator' && $this->tenant_id !== null;
    }

    /** A front-desk staff account under a tenant (limited, booking-focused access). */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
