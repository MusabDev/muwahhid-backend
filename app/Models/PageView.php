<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One page view on the public site, sent by the browser after each navigation.
 * Visitors are identified only by a hash that changes every day; no IP is kept.
 */
#[Fillable([
    'uuid', 'visitor_hash', 'session_key', 'path', 'route', 'title', 'referrer_host',
    'utm_source', 'utm_medium', 'utm_campaign', 'country', 'device', 'browser', 'os',
    'language', 'screen_width', 'is_authenticated', 'duration_seconds',
])]
class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screen_width' => 'integer',
            'is_authenticated' => 'boolean',
            'duration_seconds' => 'integer',
        ];
    }
}
