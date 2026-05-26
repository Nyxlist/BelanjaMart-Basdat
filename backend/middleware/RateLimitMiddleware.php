<?php
/**
 * Cheap IP-based rate limiter for API endpoints.
 */

class RateLimitMiddleware
{
    public static function enforce(string $bucket, int $maxPerMinute): void
    {
        $key = $bucket . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        if (!RateLimiter::hit($key, $maxPerMinute)) {
            Response::error('Too many requests, slow down.', 429);
        }
    }
}
