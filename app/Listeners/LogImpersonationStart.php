<?php

namespace App\Listeners;

use App\Models\Tenant;
use App\Models\User;
use STS\FilamentImpersonate\Events\EnterImpersonation;

class LogImpersonationStart
{
    /**
     * Write an admin audit entry (§15.5) when an admin starts impersonating a
     * tenant's operator: causer = the admin, subject = the tenant (resolved from
     * the impersonated operator's tenant_id). If the tenant can't be resolved the
     * impersonated user itself becomes the subject so the trail is never lost.
     */
    public function handle(EnterImpersonation $event): void
    {
        $operator = $event->impersonated;

        if (! $operator instanceof User) {
            return;
        }

        $subject = Tenant::query()->whereKey($operator->tenant_id)->first() ?? $operator;

        activity()
            ->performedOn($subject)
            ->causedBy($event->impersonator->getAuthIdentifier())
            ->log('impersonated');
    }
}
