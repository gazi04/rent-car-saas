<?php

use App\Concerns\ThrottlesMailQueue;

covers(ThrottlesMailQueue::class);

test('retryUntil gives the mail rate limiter a time budget instead of relying on attempt count', function () {
    $mailable = new class
    {
        use ThrottlesMailQueue;
    };

    expect($mailable->retryUntil())->toBeInstanceOf(DateTimeInterface::class)
        ->and($mailable->retryUntil())->toBeGreaterThan(now());
});
