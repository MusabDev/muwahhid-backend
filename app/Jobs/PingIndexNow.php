<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tell IndexNow engines (Bing, Yandex, Seznam, Naver…) that public pages changed,
 * so new books and durus are recrawled within minutes instead of days.
 */
class PingIndexNow implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Repeated saves of the same item (a sentence-by-sentence edit) queue one ping. */
    public int $uniqueFor = 300;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<string>  $paths  frontend paths, e.g. /books/kitab-at-tawhid
     */
    public function __construct(public array $paths) {}

    /**
     * Queue a ping when IndexNow is configured. It waits a little, so a burst of
     * admin edits to one item settles before the engines come to look.
     *
     * @param  list<string>  $paths
     */
    public static function dispatchFor(array $paths): void
    {
        if (! config('services.indexnow.key') || ! app()->isProduction()) {
            return;
        }

        static::dispatch(array_values(array_unique($paths)))->delay(now()->addMinutes(2));
    }

    public function uniqueId(): string
    {
        return md5(implode('|', $this->paths));
    }

    public function handle(): void
    {
        $site = rtrim(config('app.frontend_url'), '/');
        $key = config('services.indexnow.key');

        $response = Http::timeout(15)->post(config('services.indexnow.endpoint'), [
            'host' => parse_url($site, PHP_URL_HOST),
            'key' => $key,
            'keyLocation' => "{$site}/{$key}.txt",
            'urlList' => array_map(fn (string $path) => $site.$path, $this->paths),
        ]);

        // 200/202 accepted; 4xx means a bad key or host and retrying won't help
        if ($response->clientError()) {
            Log::warning('IndexNow rejected the ping', ['status' => $response->status(), 'body' => $response->body()]);
        } elseif ($response->failed()) {
            $response->throw();
        }
    }
}
