<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSettingSeeder extends Seeder
{
    /**
     * Seed demo branding + contact settings for the demo tenant (ardi.localhost).
     *
     * Written through Tenant::setSetting() rather than TenantSetting::create()
     * directly, since that's the allow-list + format-validated path the
     * Filament branding form itself uses (see Tenant::settingValueIsValid()).
     */
    public function run(): void
    {
        $tenant = Tenant::where('email', 'ardi@example.com')->first();

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        $settings = [
            'color_primary' => '#16a34a',
            'color_secondary' => '#166534',
            'font_family' => 'Poppins',
            'contact_phone' => '+38344123456',
            'contact_email' => 'ardi@example.com',
            'contact_address' => 'Rruga Nëna Terezë, Prishtinë',
            'footer_text_sq' => 'Ardi Rent A Car — makina me qira në Prishtinë që nga 2015.',
            'footer_text_en' => 'Ardi Rent A Car — car rentals in Prishtina since 2015.',
            'layout_home' => 'full-screen',
            'default_locale' => 'sq',
        ];

        foreach ($settings as $key => $value) {
            $tenant->setSetting($key, $value);
        }

        tenancy()->end();
    }
}
