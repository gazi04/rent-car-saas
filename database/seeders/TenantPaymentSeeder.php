<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantPaymentSeeder extends Seeder
{
    /**
     * Seed demo B2B payment history for the demo tenant (ardi.localhost).
     *
     * TenantPayment is a central model (no BelongsToTenant, admin-recorded
     * across tenants), so this runs outside any tenancy context.
     */
    public function run(): void
    {
        $tenant = Tenant::where('email', 'ardi@example.com')->first();
        $admin = User::where('email', 'admin@yourdomain.com')->first();

        if ($tenant === null || $admin === null) {
            return;
        }

        TenantPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'plan' => 'basic',
            'method' => PaymentMethod::Cash,
            'amount' => 15,
            'period_start' => now()->subMonths(2)->startOfMonth(),
            'period_end' => now()->subMonth()->startOfMonth(),
            'recorded_by' => $admin->id,
        ]);

        TenantPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'plan' => 'standard',
            'method' => PaymentMethod::BankTransfer,
            'amount' => 29,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->startOfMonth(),
            'recorded_by' => $admin->id,
        ]);
    }
}
