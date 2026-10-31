<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for the sitemap and feed payloads. Every content change bumps a version
 * number instead of forgetting keys, so feeds cached per author go stale together.
 */
class SeoCache
{
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember('seo:'.self::version().':'.$key, now()->addDay(), $callback);
    }

    public static function bump(): void
    {
        Cache::forever('seo:version', self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get('seo:version', 1);
    }
}
