<?php

declare(strict_types=1);

namespace Funnel\Security;

use Funnel\Support\Config;

/**
 * File-based rate limiting per (hashed) IP. Good enough for funnels on shared hosting.
 */
final class RateLimiter
{
    public static function hit(string $bucket, string $ip): bool
    {
        $max = (int) Config::get('security.rate_limit.max', 5);
        $window = (int) Config::get('security.rate_limit.per_minutes', 10) * 60;
        if ($max <= 0) {
            return true;
        }

        $dir = FUNNEL_ROOT . '/storage/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . hash('sha256', $bucket . '|' . $ip) . '.json';

        $now = time();
        $hits = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $hits = array_values(array_filter($hits, static fn ($t) => $t > $now - $window));
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);

        // Occasionally clean up old buckets.
        if (random_int(1, 50) === 1) {
            foreach (glob($dir . '/*.json') ?: [] as $old) {
                if (filemtime($old) < $now - $window) {
                    @unlink($old);
                }
            }
        }
        return true;
    }
}
