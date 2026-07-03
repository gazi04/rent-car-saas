<?php

namespace App\Concerns;

use Illuminate\Queue\Middleware\RateLimited;

trait ThrottlesMailQueue
{
    /**
     * Re-releases the job with a delay instead of sending once the 'mail'
     * limiter is exceeded, so booking-triggered mail bursts don't trip the
     * SMTP provider's per-second cap.
     *
     * @return array<int, RateLimited>
     */
    public function middleware(): array
    {
        return [new RateLimited('mail')];
    }
}
