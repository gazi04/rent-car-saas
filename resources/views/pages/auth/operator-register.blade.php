<?php

use App\Concerns\PasswordValidationRules;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\AvailableSubdomain;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

new #[Layout('layouts.auth')] #[Title('Start your rental business')] class extends Component
{
    use PasswordValidationRules;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subdomain = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $registered = false;

    /**
     * Register a new operator: create a pending tenant, its domain, and the
     * operator user. No panel access until a Super Admin approves it (Step 2).
     */
    public function register(): void
    {
        $this->throttleRegistration();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'subdomain' => ['required', 'string', new AvailableSubdomain],
            'password' => $this->passwordRules(),
        ]);

        $user = $this->createOperator($validated);

        // Both of these stay outside createOperator()'s transaction: logging in
        // writes the session, and Registered dispatches the verification email —
        // neither can be rolled back if an insert loses a race.
        Auth::login($user);
        event(new Registered($user));

        $this->registered = true;
    }

    /**
     * Three limiters, all checked and incremented *before* validation.
     *
     * Counting attempts rather than successes is the point: this used to hit the
     * limiter only after validation passed, so a failed attempt was free and the
     * cap only ever bounded successful signups.
     *
     * - per IP: the ordinary abuser.
     * - per email: one address spraying subdomains through a proxy pool.
     * - global: neither of the above stops rotating IPs with fresh addresses from
     *   mass-creating pending tenants, each squatting a subdomain until an admin
     *   purges it. This is the floor. See config/tenancy.php signup_hourly_cap.
     */
    protected function throttleRegistration(): void
    {
        $ipKey = 'operator-register:'.request()->ip();
        $emailKey = 'operator-register:email:'.sha1(Str::lower(trim($this->email)));
        $globalKey = 'operator-register:global';
        $globalCap = config()->integer('tenancy.signup_hourly_cap', 20);

        if (RateLimiter::tooManyAttempts($globalKey, maxAttempts: $globalCap)) {
            throw ValidationException::withMessages([
                'email' => [__('New registrations are temporarily paused. Please try again later.')],
            ]);
        }

        if (
            RateLimiter::tooManyAttempts($ipKey, maxAttempts: 3)
            || RateLimiter::tooManyAttempts($emailKey, maxAttempts: 3)
        ) {
            throw ValidationException::withMessages([
                'email' => [__('Too many registration attempts. Please try again later.')],
            ]);
        }

        RateLimiter::hit($ipKey, decaySeconds: 3600);
        RateLimiter::hit($emailKey, decaySeconds: 86400);
        RateLimiter::hit($globalKey, decaySeconds: 3600);
    }

    /**
     * Create the tenant, its domain and the owner in one transaction.
     *
     * Both AvailableSubdomain's "already taken" check and the `unique:users,email`
     * rule are reads followed by an insert, so two concurrent signups can pass them
     * and still collide. The `domains.domain` and `users.email` unique indexes are
     * the real arbiters; this turns their violations back into field errors instead
     * of a 500, and the transaction stops a lost race from leaving an orphan tenant.
     *
     * Each insert is caught at its own call site rather than catching once around
     * the whole transaction: by the time an outer catch runs, the rollback has
     * already erased the evidence of which index fired, and asking the database
     * again would answer about a row that no longer exists.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function createOperator(array $validated): User
    {
        /** @var string $subdomain */
        $subdomain = $validated['subdomain'];

        return DB::transaction(function () use ($validated, $subdomain): User {
            $tenant = Tenant::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?: null,
                'status' => 'pending',
                'plan' => Plan::trialSlug(),
            ]);

            try {
                $tenant->domains()->create([
                    'domain' => AvailableSubdomain::fullDomain($subdomain),
                ]);
            } catch (DomainOccupiedByOtherTenantException|UniqueConstraintViolationException) {
                // Two distinct arbiters, and both have to be caught. stancl's Domain
                // model re-checks occupancy on `saving` and throws first — but that
                // check is itself a read before an insert, so under real concurrency
                // the `domains.domain` unique index is what finally decides.
                throw ValidationException::withMessages([
                    'subdomain' => [__('This subdomain is already taken.')],
                ]);
            }

            $user = new User;
            $user->forceFill([
                'tenant_id' => $tenant->id,
                'role' => 'operator',
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            try {
                $user->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'email' => [__('An account with this email address already exists.')],
                ]);
            }

            return $user;
        });
    }
}; ?>

<div>
    @if ($registered)
        <div class="flex flex-col gap-6 text-center">
            <flux:heading size="xl">{{ __('Account under review') }}</flux:heading>
            <flux:text>
                {{ __('Thanks for signing up. Check your inbox to verify your email address, and an administrator will review your account shortly. You will be able to sign in at your subdomain once it is approved.') }}
            </flux:text>
            <flux:badge color="amber" class="mx-auto">{{ $subdomain }}.{{ config('tenancy.tenant_base_domain', 'localhost') }}</flux:badge>
        </div>
    @else
        <div class="flex flex-col gap-6">
            <x-auth-header
                :title="__('Create your operator account')"
                :description="__('Register your rental business to get your own branded booking site')"
            />

            <form wire:submit="register" class="flex flex-col gap-6">
                <flux:input
                    wire:model="name"
                    :label="__('Business name')"
                    type="text"
                    required
                    autofocus
                    :placeholder="__('Ardi Rent A Car')"
                />

                <flux:input
                    wire:model="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    placeholder="email@example.com"
                />

                <flux:input
                    wire:model="phone"
                    :label="__('Phone (optional)')"
                    type="tel"
                />

                <flux:input
                    wire:model="subdomain"
                    :label="__('Subdomain')"
                    type="text"
                    required
                    :placeholder="__('ardi')"
                    :description="__('Your booking site will live here.')"
                >
                    <x-slot name="iconTrailing">
                        <span class="text-sm text-zinc-500">.{{ config('tenancy.tenant_base_domain', 'localhost') }}</span>
                    </x-slot>
                </flux:input>

                <flux:input
                    wire:model="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                />

                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    viewable
                />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Create account') }}
                </flux:button>
            </form>

            <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Already approved?') }}</span>
                <span>{{ __('Sign in at your own subdomain.') }}</span>
            </div>
        </div>
    @endif
</div>
