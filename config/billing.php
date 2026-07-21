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

    /**
     * Days relative to paid_until on which a reminder is emailed.
     * Positive = that many days before the period ends.
     * Negative = that many days after it lapsed, i.e. inside the grace window —
     * the operator's last warning before automatic suspension.
     */
    'reminder_days' => [7, 1, -1],
];
