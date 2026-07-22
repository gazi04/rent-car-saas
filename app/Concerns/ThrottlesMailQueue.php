<?php

namespace App\Concerns;

use DateTimeInterface;
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

    /**
     * A rate-limiter release consumes an attempt just like a real failure does
     * (DatabaseQueue::release() carries the incremented attempts count forward),
     * so under `--tries=1` a single throttle release is enough to fail the job
     * before it ever sends. retryUntil() takes precedence over maxTries in the
     * worker's stale-attempt check, so throttled mail gets a real time budget
     * to drain through the 1/sec limiter instead of being killed by attempt count.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }
}
