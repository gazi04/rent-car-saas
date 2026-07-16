<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    /**
     * Seed demo promo codes for the demo tenant (ardi.localhost).
     *
     * Created inside the tenant context so BelongsToTenant auto-fills tenant_id.
     */
    public function run(): void
    {
        $tenant = Tenant::where('email', 'ardi@example.com')->first();

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        PromoCode::factory()->create(['code' => 'SUMMER10']);
        PromoCode::factory()->fixed(20)->create(['code' => 'FLAT20']);
        PromoCode::factory()->expired()->create(['code' => 'OLDCODE']);

        tenancy()->end();
    }
}
