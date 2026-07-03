<?php

/*
|--------------------------------------------------------------------------
| Manual B2B billing
|--------------------------------------------------------------------------
|
| The trial is just the first free paid period: approving a tenant sets
| paid_until = now() + trial_days. From then on the daily
| tenants:process-subscriptions sweep drives the whole lifecycle —
| reminder emails at each of reminder_days before paid_until, then a
| grace_days window after it lapses, then automatic suspension.
|
*/

return [
    'trial_days' => 30,

    'grace_days' => 7,

    /** Days before paid_until on which a renewal reminder is emailed. */
    'reminder_days' => [7, 1],
];
