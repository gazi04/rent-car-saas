<x-layouts::auth :title="__('Account status')">
    <div class="flex flex-col gap-6 text-center">
        @php
            $message = match ($status) {
                'pending' => __('Your account is under review. You will be able to sign in once an administrator approves it.'),
                'suspended' => __('Your account has been suspended. Please contact support to restore access.'),
                'cancelled' => __('Your account has been cancelled. Please contact support if you believe this is a mistake.'),
                default => __('Your account is not active yet.'),
            };
        @endphp

        <flux:heading size="xl">{{ $name }}</flux:heading>

        <flux:badge :color="$status === 'pending' ? 'amber' : 'red'" class="mx-auto">
            {{ ucfirst($status) }}
        </flux:badge>

        <flux:text>{{ $message }}</flux:text>
    </div>
</x-layouts::auth>
