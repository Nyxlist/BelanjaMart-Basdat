<?php
/**
 * File-based rate limiter.  Lightweight enough that we don't need to
 * pull in Redis or memcached for a small/medium app.
 */

class RateLimiter
{
    public static function hit(string $key, int $maxPerMinute): bool
    {
        $dir = config('storage.logs') . '/ratelimit';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $file = $dir . '/' . md5($key) . '.json';

        $now    = time();
        $window = 60;
        $data   = [];
        if (is_file($file)) {
            $raw = json_decode((string) file_get_contents($file), true);
            if (is_array($raw)) $data = $raw;
        }

        // Drop hits older than the window
        $data = array_values(array_filter($data, fn($t) => $t > $now - $window));
        if (count($data) >= $maxPerMinute) {
            return false;
        }
        $data[] = $now;
        @file_put_contents($file, json_encode($data));
        return true;
    }
}
