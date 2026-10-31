<?php

namespace App\Models\Concerns;

use App\Jobs\PingIndexNow;
use App\Support\SeoCache;

/**
 * Content that appears in the sitemap and feeds: any change refreshes their cache, and a
 * model with public pages (`seoPaths()`) asks search engines to recrawl them via IndexNow.
 */
trait RefreshesSeo
{
    public static function bootRefreshesSeo(): void
    {
        static::saved(fn (self $model) => $model->refreshSeo());
        static::deleted(fn (self $model) => $model->refreshSeo());
    }

    protected function refreshSeo(): void
    {
        SeoCache::bump();

        if (method_exists($this, 'seoPaths') && $paths = $this->seoPaths()) {
            PingIndexNow::dispatchFor($paths);
        }
    }
}
