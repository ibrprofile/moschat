<?php

declare(strict_types=1);

namespace MosChat\Core;

final class RateLimiter
{
    public static function attempt(string $key, int $max, int $windowSeconds): bool
    {
        $now = time();
        $row = Database::fetch('SELECT hits, window_start FROM rate_limits WHERE rate_key = ?', [$key]);

        if (!$row) {
            Database::insert('rate_limits', [
                'rate_key' => $key,
                'hits' => 1,
                'window_start' => gmdate('Y-m-d H:i:s', $now),
            ]);
            return true;
        }

        $windowStart = strtotime($row['window_start'] . ' UTC') ?: 0;
        if ($now - $windowStart >= $windowSeconds) {
            Database::update('rate_limits', [
                'hits' => 1,
                'window_start' => gmdate('Y-m-d H:i:s', $now),
            ], 'rate_key = ?', [$key]);
            return true;
        }

        if ((int) $row['hits'] >= $max) {
            return false;
        }

        Database::query('UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?', [$key]);
        return true;
    }
}
