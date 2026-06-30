<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Test-only model used to prove tenant data isolation before real
 * tenant-scoped business models (Vehicle, Booking, …) exist.
 *
 * It demonstrates the exact convention every future tenant-owned model
 * must follow: a `tenant_id` column plus the BelongsToTenant trait.
 *
 * @property string $tenant_id
 * @property string $name
 */
class ScopedItem extends Model
{
    use BelongsToTenant;

    protected $table = 'scoped_items';

    protected $guarded = [];
}
