<?php

namespace App\Services;

use App\Models\PageView;
use Carbon\CarbonImmutable;
use DeviceDetector\Cache\LaravelCache;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cookieless page-view analytics: records views sent by the site's browser beacon
 * and builds the reports shown in the admin panel.
 */
class Analytics
{
    /** Timezone the reports are bucketed in; the audience is in Bangladesh. */
    public const TIMEZONE = 'Asia/Dhaka';

    /** A visit ends after this many minutes without a new view. */
    private const SESSION_GAP_MINUTES = 30;

    private const LIST_LIMIT = 20;

    /**
     * Daily-rotating hash of IP and user agent. The salt is thrown away after two days,
     * so a hash can't be linked to an IP or to the same visitor on another day.
     */
    public function visitorHash(Request $request): string
    {
        $day = now(self::TIMEZONE)->toDateString();
        $salt = Cache::remember("analytics:salt:{$day}", now()->addDays(2), fn () => Str::random(32));

        return hash('sha256', $salt.'|'.$this->clientIp($request).'|'.$request->userAgent().'|'.$request->getHost());
    }

    /**
     * Store a view unless it comes from a bot.
     *
     * @param  array{id: string, path: string, route?: string|null, title?: string|null, referrer?: string|null, utm_source?: string|null, utm_medium?: string|null, utm_campaign?: string|null, language?: string|null, screen_width?: int|null}  $data
     */
    public function record(Request $request, array $data): void
    {
        $detector = new DeviceDetector((string) $request->userAgent(), ClientHints::factory($request->server->all()));
        $detector->setCache(new LaravelCache);
        $detector->discardBotInformation();
        $detector->parse();

        if ($detector->isBot()) {
            return;
        }

        $visitor = $this->visitorHash($request);

        $session = PageView::query()
            ->where('visitor_hash', $visitor)
            ->where('created_at', '>=', now()->subMinutes(self::SESSION_GAP_MINUTES))
            ->latest('created_at')
            ->value('session_key');

        $country = strtoupper((string) $request->header('CF-IPCountry'));

        PageView::query()->insertOrIgnore([
            'uuid' => $data['id'],
            'visitor_hash' => $visitor,
            'session_key' => $session ?? (string) Str::uuid(),
            'path' => Str::limit(strtok($data['path'], '?#') ?: '/', 500, ''),
            'route' => Str::limit($data['route'] ?? $data['path'], 150, ''),
            'title' => isset($data['title']) ? Str::limit($data['title'], 300, '') : null,
            'referrer_host' => $this->referrerHost($data['referrer'] ?? null),
            'utm_source' => $this->clean($data['utm_source'] ?? null),
            'utm_medium' => $this->clean($data['utm_medium'] ?? null),
            'utm_campaign' => $this->clean($data['utm_campaign'] ?? null),
            'country' => preg_match('/^[A-Z]{2}$/', $country) && ! in_array($country, ['XX', 'T1'], true) ? $country : null,
            'device' => $detector->getDeviceName() ?: 'unknown',
            'browser' => Str::limit((string) ($detector->getClient('name') ?: 'Unknown'), 50, ''),
            'os' => Str::limit((string) ($detector->getOs('name') ?: 'Unknown'), 50, ''),
            'language' => isset($data['language']) ? (Str::lower(Str::before($data['language'], '-')) ?: null) : null,
            'screen_width' => $data['screen_width'] ?? null,
            'is_authenticated' => $request->user('sanctum') !== null,
            'created_at' => now(),
        ]);
    }

    /**
     * Save how long a view was on screen. Only the visitor who made the view can update it.
     */
    public function engage(Request $request, string $id, int $seconds): void
    {
        PageView::query()
            ->where('uuid', $id)
            ->where('visitor_hash', $this->visitorHash($request))
            ->where('created_at', '>=', now()->subDay())
            ->update(['duration_seconds' => min(max($seconds, 0), 1800)]);
    }

    /**
     * The full report for a period, with the period just before it for comparison.
     *
     * @return array<string, mixed>
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to, ?string $path = null): array
    {
        $key = 'analytics:report:'.md5($from->toIso8601String().$to->toIso8601String().$path);

        return Cache::remember($key, now()->addMinutes(2), function () use ($from, $to, $path) {
            $previousFrom = $from->sub($from->diff($to));

            return [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'interval' => $this->interval($from, $to),
                'summary' => $this->summary($from, $to, $path),
                'previous' => $this->summary($previousFrom, $from, $path),
                'timeseries' => $this->timeseries($from, $to, $path),
                'pages' => $this->pages($from, $to, $path),
                'routes' => $this->breakdown('route', $from, $to, $path),
                'entry_pages' => $this->edgePages($from, $to, $path, 'asc'),
                'exit_pages' => $this->edgePages($from, $to, $path, 'desc'),
                'referrers' => $this->entryBreakdown('referrer_host', $from, $to, $path),
                'utm_sources' => $this->entryBreakdown('utm_source', $from, $to, $path, skipEmpty: true),
                'utm_campaigns' => $this->entryBreakdown('utm_campaign', $from, $to, $path, skipEmpty: true),
                'countries' => $this->breakdown('country', $from, $to, $path),
                'devices' => $this->breakdown('device', $from, $to, $path),
                'browsers' => $this->breakdown('browser', $from, $to, $path),
                'os' => $this->breakdown('os', $from, $to, $path),
                'languages' => $this->breakdown('language', $from, $to, $path),
                'screens' => $this->screens($from, $to, $path),
                'auth' => $this->breakdown('is_authenticated', $from, $to, $path),
            ];
        });
    }

    /**
     * Who is on the site in the last five minutes, and on which pages.
     *
     * @return array{visitors: int, pages: list<array{label: string, title: string|null, visitors: int}>}
     */
    public function realtime(): array
    {
        $recent = fn () => DB::table('page_views')->where('created_at', '>=', now()->subMinutes(5));

        return [
            'visitors' => (int) $recent()->distinct()->count('visitor_hash'),
            'pages' => $recent()
                ->selectRaw('path AS label, MAX(title) AS title, COUNT(DISTINCT visitor_hash) AS visitors')
                ->groupBy('path')
                ->orderByDesc('visitors')
                ->limit(10)
                ->get()
                ->map(fn (object $row) => ['label' => $row->label, 'title' => $row->title, 'visitors' => (int) $row->visitors])
                ->all(),
        ];
    }

    /**
     * Hour buckets for a day or two, months for long ranges, days otherwise.
     */
    private function interval(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $days = $from->diffInDays($to);

        return match (true) {
            $days <= 2 => 'hour',
            $days > 92 => 'month',
            default => 'day',
        };
    }

    /**
     * @return array{visitors: int, pageviews: int, visits: int, bounce_rate: float, pages_per_visit: float, avg_duration: int}
     */
    private function summary(CarbonImmutable $from, CarbonImmutable $to, ?string $path): array
    {
        $totals = $this->views($from, $to, $path)
            ->selectRaw('COUNT(*) AS pageviews, COUNT(DISTINCT visitor_hash) AS visitors, AVG(duration_seconds) AS avg_duration')
            ->first();

        $sessions = DB::query()
            ->fromSub($this->views($from, $to, $path)->selectRaw('session_key, COUNT(*) AS n')->groupBy('session_key'), 's')
            ->selectRaw('COUNT(*) AS visits, COUNT(*) FILTER (WHERE n = 1) AS bounces, AVG(n) AS pages_per_visit')
            ->first();

        $visits = (int) $sessions->visits;

        return [
            'visitors' => (int) $totals->visitors,
            'pageviews' => (int) $totals->pageviews,
            'visits' => $visits,
            'bounce_rate' => $visits ? round($sessions->bounces / $visits * 100, 1) : 0.0,
            'pages_per_visit' => round((float) $sessions->pages_per_visit, 2),
            'avg_duration' => (int) round((float) $totals->avg_duration),
        ];
    }

    /**
     * Views and visitors per bucket, with empty buckets filled in.
     *
     * @return list<array{date: string, pageviews: int, visitors: int}>
     */
    private function timeseries(CarbonImmutable $from, CarbonImmutable $to, ?string $path): array
    {
        $interval = $this->interval($from, $to);
        $bucket = "date_trunc('{$interval}', created_at AT TIME ZONE 'UTC' AT TIME ZONE '".self::TIMEZONE."')";

        $rows = $this->views($from, $to, $path)
            ->selectRaw("{$bucket} AS bucket, COUNT(*) AS pageviews, COUNT(DISTINCT visitor_hash) AS visitors")
            ->groupByRaw($bucket)
            ->get()
            ->keyBy(fn (object $row) => CarbonImmutable::parse($row->bucket, self::TIMEZONE)->toIso8601String());

        $series = [];
        $cursor = $from->setTimezone(self::TIMEZONE)->startOf($interval);
        $end = $to->setTimezone(self::TIMEZONE);

        while ($cursor < $end) {
            $row = $rows->get($cursor->toIso8601String());
            $series[] = [
                'date' => $cursor->toIso8601String(),
                'pageviews' => (int) ($row->pageviews ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
            ];
            $cursor = $cursor->add(1, $interval);
        }

        return $series;
    }

    /**
     * @return list<array{label: string, title: string|null, views: int, visitors: int, avg_duration: int|null}>
     */
    private function pages(CarbonImmutable $from, CarbonImmutable $to, ?string $path): array
    {
        return $this->views($from, $to, $path)
            ->selectRaw('path AS label, MAX(title) AS title, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, AVG(duration_seconds) AS avg_duration')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (object $row) => [
                'label' => $row->label,
                'title' => $row->title,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'avg_duration' => $row->avg_duration === null ? null : (int) round((float) $row->avg_duration),
            ])
            ->all();
    }

    /**
     * Views and visitors grouped by one column; null is sent as null so the UI can name it.
     *
     * @return list<array{label: string|null, views: int, visitors: int}>
     */
    private function breakdown(string $column, CarbonImmutable $from, CarbonImmutable $to, ?string $path): array
    {
        return $this->rows($this->views($from, $to, $path), $column);
    }

    /**
     * Like breakdown(), but counted on the first view of each visit, which is where the
     * referrer and campaign of a visit are recorded.
     *
     * @return list<array{label: string|null, views: int, visitors: int}>
     */
    private function entryBreakdown(string $column, CarbonImmutable $from, CarbonImmutable $to, ?string $path, bool $skipEmpty = false): array
    {
        $entries = DB::query()->fromSub($this->firstViews($from, $to, $path, 'asc'), 'e')
            ->when($skipEmpty, fn (Builder $query) => $query->whereNotNull($column));

        return $this->rows($entries, $column);
    }

    /**
     * Pages where visits started (asc) or ended (desc).
     *
     * @return list<array{label: string|null, views: int, visitors: int}>
     */
    private function edgePages(CarbonImmutable $from, CarbonImmutable $to, ?string $path, string $direction): array
    {
        return $this->rows(DB::query()->fromSub($this->firstViews($from, $to, $path, $direction), 'e'), 'path');
    }

    /**
     * Screen widths grouped into the usual phone, tablet and desktop sizes.
     *
     * @return list<array{label: string|null, views: int, visitors: int}>
     */
    private function screens(CarbonImmutable $from, CarbonImmutable $to, ?string $path): array
    {
        $size = "CASE WHEN screen_width IS NULL THEN NULL WHEN screen_width < 576 THEN 'mobile' WHEN screen_width < 992 THEN 'tablet' WHEN screen_width < 1440 THEN 'laptop' ELSE 'desktop' END";

        return $this->rows(DB::query()->fromSub($this->views($from, $to, $path)->selectRaw("*, {$size} AS screen"), 's'), 'screen');
    }

    /**
     * @return list<array{label: string|null, views: int, visitors: int}>
     */
    private function rows(Builder $query, string $column): array
    {
        return $query
            ->selectRaw("{$column} AS label, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors")
            ->groupBy($column)
            ->orderByDesc('visitors')
            ->orderByDesc('views')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (object $row) => [
                'label' => is_bool($row->label) ? ($row->label ? 'true' : 'false') : $row->label,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    /**
     * The first (asc) or last (desc) view of every visit in the period.
     */
    private function firstViews(CarbonImmutable $from, CarbonImmutable $to, ?string $path, string $direction): Builder
    {
        return $this->views($from, $to, $path)
            ->selectRaw('DISTINCT ON (session_key) *')
            ->orderBy('session_key')
            ->orderBy('created_at', $direction);
    }

    private function views(CarbonImmutable $from, CarbonImmutable $to, ?string $path): Builder
    {
        return DB::table('page_views')
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->utc())
            ->when($path, fn (Builder $query) => $query->where('path', $path));
    }

    /**
     * Cloudflare sends the visitor's address in its own header; otherwise use the connection.
     */
    private function clientIp(Request $request): string
    {
        return (string) ($request->header('CF-Connecting-IP') ?? $request->ip());
    }

    /**
     * Host of an outside referrer without "www."; links from the site itself are dropped.
     */
    private function referrerHost(?string $referrer): ?string
    {
        $host = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = Str::lower(preg_replace('/^www\./i', '', $host));
        $own = Str::lower(preg_replace('/^www\./i', '', (string) parse_url((string) config('app.frontend_url'), PHP_URL_HOST)));

        return $host === $own ? null : Str::limit($host, 255, '');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit(Str::lower($value), 100, '');
    }
}
