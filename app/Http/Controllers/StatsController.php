<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookPage;
use App\Models\Dars;
use App\Models\DarsSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Site-wide counts for the home and library pages: published books and dars series,
     * pages that have a Bangla translation, and the total length of published durus.
     */
    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember('site-stats', now()->addMinutes(10), function () {
            $translatedPages = BookPage::query()
                ->whereHas('book', fn ($query) => $query->published())
                ->whereExists(fn ($query) => $query
                    ->select(DB::raw(1))
                    ->from('book_sentences')
                    ->whereColumn('book_sentences.page_id', 'book_pages.id')
                    ->where('book_sentences.text_bn_plain', '<>', ''))
                ->count();

            // A dars without a recorded duration counts up to the end of its transcript
            $durusSeconds = (float) Dars::query()
                ->whereHas('series', fn ($query) => $query->published())
                ->selectRaw('COALESCE(durus.duration_seconds, (SELECT MAX(COALESCE(end_time, start_time)) FROM dars_segments WHERE dars_segments.dars_id = durus.id), 0) AS seconds')
                ->get()
                ->sum('seconds');

            return [
                'books' => Book::published()->count(),
                'dars_series' => DarsSeries::published()->count(),
                'translated_pages' => $translatedPages,
                'durus_seconds' => (int) round($durusSeconds),
            ];
        });

        return response()->json($stats);
    }
}
