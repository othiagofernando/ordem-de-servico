<?php

namespace App\Domains\Auth\Support;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

class AuthRateLimiter
{
    public function ensureNotLimited(string $key, int $maxAttempts, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new ThrottleRequestsException('Too many authentication attempts. Please try again later.');
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
