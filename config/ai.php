<?php

/*
|--------------------------------------------------------------------------
| AI operator features
|--------------------------------------------------------------------------
|
| Every feature is a single chat request/response against the OpenAI API
| (credentials live in config/openai.php). Features are gated per plan via
| the PlanFeature Ai* toggles, which default OFF — an unseeded plan never
| burns API credit.
|
*/

return [
    'model' => env('OPENAI_MODEL', 'gpt-5-mini'),

    /** Max vehicle photos attached to a listing-writer request (image-token cap). */
    'max_photos' => 3,

    /** How many days of data the weekly business summary covers. */
    'summary_period_days' => 7,
];
