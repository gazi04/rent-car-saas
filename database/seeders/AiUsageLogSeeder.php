<?php

namespace Database\Seeders;

use App\Models\AiUsageLog;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AiUsageLogSeeder extends Seeder
{
    /**
     * Seed demo AI usage log rows for the demo tenant (ardi.localhost), across
     * all three AI features and spread over the past week.
     *
     * AiUsageLog is a central model (no BelongsToTenant, administered
     * cross-tenant from the admin panel), so this runs outside any tenancy context.
     */
    public function run(): void
    {
        $tenant = Tenant::where('email', 'ardi@example.com')->first();

        if ($tenant === null) {
            return;
        }

        $features = ['listing', 'summary', 'pricing'];

        foreach ($features as $index => $feature) {
            AiUsageLog::factory()->count(2)->create([
                'tenant_id' => $tenant->id,
                'feature' => $feature,
                'created_at' => now()->subDays($index + 1),
            ]);
        }
    }
}
