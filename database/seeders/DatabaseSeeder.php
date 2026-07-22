<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            PlanSeeder::class,
            TenantSeeder::class,
            VehicleSeeder::class,
            CustomerSeeder::class,
            PromoCodeSeeder::class,
            BookingSeeder::class,
            ServiceRecordSeeder::class,
            ContractSeeder::class,
            ReviewSeeder::class,
            TenantSettingSeeder::class,
            TenantPaymentSeeder::class,
            AiUsageLogSeeder::class,
            AiBusinessSummarySeeder::class,
        ]);
    }
}
