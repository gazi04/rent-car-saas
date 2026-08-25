<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the per-page layout settings left over from the multi-variant storefront.
 *
 * The public site used to offer seven home layouts, three listing layouts and
 * three vehicle-detail layouts, chosen per tenant. They collapsed to one shell
 * per page: on a phone every variant rendered as the same single column, so the
 * choice only ever differed on desktop while costing thirteen partials that
 * drifted apart from each other.
 *
 * Nothing reads these keys any more — the Blade lookups, the config allow-list
 * and the Filament selects are all gone — so the rows are inert rather than
 * dangerous. They are removed anyway because tenant_settings has a unique index
 * on (tenant_id, key): left in place they would show up in any settings dump
 * forever, looking like live configuration that no longer does anything.
 *
 * down() is intentionally empty. Re-inserting a layout choice would point at
 * partials that no longer exist, and the correct default is now "there is only
 * one layout" — there is nothing to restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenant_settings')
            ->whereIn('key', ['layout_home', 'layout_vehicles', 'layout_vehicle_show'])
            ->delete();
    }

    public function down(): void
    {
        // No-op — see the class docblock.
    }
};
