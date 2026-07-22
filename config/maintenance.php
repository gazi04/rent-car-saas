<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vehicle maintenance tracking
|--------------------------------------------------------------------------
|
| Service logging is free on every plan; the daily sweep (reminders +
| auto-block) only runs for tenants whose plan enables
| PlanFeature::MaintenanceReminders.
|
*/
return [
    /** Curated service types offered in the ServiceRecord form/table. */
    'service_types' => ['oil_change', 'tyres', 'inspection', 'brakes', 'other'],

    /** Days-before-due at which a reminder is sent (checked daily, once each). */
    'reminder_days' => [7, 1],

    /** Length of the auto-block window created once a service is due. */
    'block_days' => 3,
];
