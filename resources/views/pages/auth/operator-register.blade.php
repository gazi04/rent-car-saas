<?php

use App\Concerns\PasswordValidationRules;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Stancl\Tenancy\Database\Models\Domain;

new #[Layout('layouts.auth')] #[Title('Start your rental business')] class extends Component {
    use PasswordValidationRules;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subdomain = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $registered = false;

    /**
     * Subdomains that may not be claimed by an operator (reserved for the platform).
     *
     * @var list<string>
     */
    protected array $reservedSubdomains = ['admin', 'www', 'api', 'app', 'mail', 'ftp', 'dashboard', 'support'];

    /**
     * Register a new operator: create a pending tenant, its domain, and the
     * operator user. No panel access until a Super Admin approves it (Step 2).
     */
    public function register(): void
    {
        $base = config('tenancy.tenant_base_domain', 'localhost');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'subdomain' => [
                'required', 'string', 'lowercase', 'max:63',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn($this->reservedSubdomains),
                function (string $attribute, mixed $value, Closure $fail) use ($base): void {
                    if (Domain::query()->where('domain', $value.'.'.$base)->exists()) {
                        $fail(__('This subdomain is already taken.'));
                    }
                },
            ],
            'password' => $this->passwordRules(),
        ]);

        $tenant = Tenant::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'status' => 'pending',
            'plan' => 'trial',
        ]);

        $tenant->domains()->create([
            'domain' => $validated['subdomain'].'.'.$base,
        ]);

        $user = new User;
        $user->forceFill([
            'tenant_id' => $tenant->id,
            'role' => 'operator',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ])->save();

        Auth::login($user);
        event(new Registered($user));

        $this->registered = true;
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
