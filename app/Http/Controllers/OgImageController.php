<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Dars;
use App\Models\DarsSeries;
use App\Services\OgImageRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * GET /og/{type}/{key}.jpg — the share card behind og:image. Unpublished items get no card.
 * If rendering fails the site's default card is served, so a link preview never breaks.
 */
class OgImageController extends Controller
{
    public function __invoke(OgImageRenderer $renderer, string $type, string $key): Response
    {
        $item = match ($type) {
            'book' => Book::published()->with(['author', 'explainer'])->where('slug', $key)->firstOrFail(),
            'author' => Author::where('slug', $key)->firstOrFail(),
            'series' => DarsSeries::published()->with(['author', 'book'])->where('slug', $key)->firstOrFail(),
            'dars' => $this->dars($key),
            'site' => null,
            default => abort(404),
        };

        try {
            $path = $renderer->path($item);
        } catch (Throwable $e) {
            Log::warning('OG image render failed', ['type' => $type, 'key' => $key, 'error' => $e->getMessage()]);

            return response()->file(public_path('og-default.jpg'), ['Cache-Control' => 'public, max-age=300']);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
        ]);
    }

    /**
     * Durus are keyed "{series-slug}-{number}", matching their /durus/{series}/{number} URL.
     */
    private function dars(string $key): Dars
    {
        abort_unless(preg_match('/^(.+)-(\d+)$/', $key, $match), 404);

        $series = DarsSeries::published()->with('author')->where('slug', $match[1])->firstOrFail();
        $dars = $series->durus()->where('number', (int) $match[2])->firstOrFail();

        return $dars->setRelation('series', $series);
    }
}
