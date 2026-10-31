<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Dars;
use App\Models\DarsSeries;
use App\Support\MarkdownText;
use App\Support\SeoCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Compact, cached listings of every public page for the web app's sitemaps and RSS feeds.
 */
class SeoController extends Controller
{
    /**
     * Everything the sitemaps list: books with their page numbers, authors with public work,
     * series with their durus, and the categories that have content.
     */
    public function sitemap(): JsonResponse
    {
        return response()->json(SeoCache::remember('sitemap', fn () => [
            'books' => Book::published()
                ->with(['author:id,name_bn', 'pages:id,book_id,number'])
                ->orderBy('id')
                ->get()
                ->map(fn (Book $book) => [
                    'slug' => $book->slug,
                    'title_bn' => $book->title_bn,
                    'title_ar' => $book->title_ar,
                    'author' => $book->author?->name_bn,
                    'cover_url' => $book->coverUrl(),
                    'updated_at' => $book->updated_at?->toIso8601String(),
                    'pages' => $book->pages->pluck('number')->all(),
                ])
                ->all(),
            'authors' => Author::query()
                ->where(fn (Builder $query) => $query
                    ->whereHas('books', fn (Builder $books) => $books->published())
                    ->orWhereHas('explainedBooks', fn (Builder $books) => $books->published())
                    ->orWhereHas('darsSeries', fn (Builder $series) => $series->published()))
                ->orderBy('id')
                ->get(['slug', 'name_bn', 'updated_at'])
                ->map(fn (Author $author) => [
                    'slug' => $author->slug,
                    'name_bn' => $author->name_bn,
                    'updated_at' => $author->updated_at?->toIso8601String(),
                ])
                ->all(),
            'series' => DarsSeries::published()
                ->with(['author:id,name_bn', 'durus' => fn ($query) => $query->withMax('segments', 'end_time')])
                ->orderBy('id')
                ->get()
                ->map(fn (DarsSeries $series) => [
                    'slug' => $series->slug,
                    'title_bn' => $series->title_bn,
                    'about' => $this->summary($series->about_md),
                    'author' => $series->author?->name_bn,
                    'updated_at' => $series->updated_at?->toIso8601String(),
                    'durus' => $series->durus->map(fn (Dars $dars) => $this->darsItem($dars))->all(),
                ])
                ->all(),
            'categories' => Category::query()
                ->withCount(['books' => fn (Builder $query) => $query->published(), 'darsSeries' => fn (Builder $query) => $query->published()])
                ->orderBy('position')
                ->get()
                ->map(fn (Category $category) => [
                    'slug' => $category->slug,
                    'name_bn' => $category->name_bn,
                    'books' => $category->books_count,
                    'series' => $category->dars_series_count,
                ])
                ->filter(fn (array $category) => $category['books'] || $category['series'])
                ->values()
                ->all(),
        ]));
    }

    /**
     * The newest books and durus, for RSS. `type` narrows to books or durus,
     * `author` to one scholar's work (as author, explainer or teacher).
     */
    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:all,books,durus'],
            'author' => ['nullable', 'string', 'max:255'],
        ]);
        $type = $data['type'] ?? 'all';
        $authorSlug = $data['author'] ?? null;

        $author = $authorSlug ? Author::where('slug', $authorSlug)->firstOrFail() : null;

        return response()->json(SeoCache::remember("feed:{$type}:{$authorSlug}", function () use ($type, $author) {
            $items = collect();

            if ($type !== 'durus') {
                $items = $items->concat($this->feedBooks($author));
            }

            if ($type !== 'books') {
                $items = $items->concat($this->feedDurus($author));
            }

            return [
                'author' => $author?->only(['slug', 'name_bn', 'name_ar']),
                // plain arrays: the cache store only unserializes scalars and arrays
                'items' => $items->sortByDesc('published_at')->take(50)->values()->all(),
            ];
        }));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function feedBooks(?Author $author): Collection
    {
        return Book::published()
            ->with(['author:id,slug,name_bn', 'explainer:id,slug,name_bn', 'category:id,name_bn'])
            ->when($author, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('author_id', $author->id)->orWhere('explainer_id', $author->id)))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Book $book) => [
                'type' => 'book',
                'path' => '/books/'.$book->slug,
                'title' => $book->title_bn,
                'title_ar' => $book->title_ar,
                'summary' => $this->summary($book->about_md),
                'author' => ($book->is_sharh && $book->explainer ? $book->explainer : $book->author)?->name_bn,
                'category' => $book->category?->name_bn,
                'image' => $book->coverUrl(),
                'published_at' => $book->created_at?->toIso8601String(),
                'updated_at' => $book->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function feedDurus(?Author $author): Collection
    {
        return Dars::query()
            ->whereHas('series', fn (Builder $query) => $query->published()->when($author, fn (Builder $inner) => $inner->where('author_id', $author->id)))
            ->with(['series:id,slug,title_bn,author_id,category_id', 'series.author:id,name_bn', 'series.category:id,name_bn'])
            ->withMax('segments', 'end_time')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Dars $dars) => [
                'type' => 'dars',
                'path' => '/durus/'.$dars->series->slug.'/'.$dars->number,
                'title' => $dars->title_bn.' — '.$dars->series->title_bn,
                'title_ar' => $dars->title_ar,
                'summary' => null,
                'author' => $dars->series->author?->name_bn,
                'category' => $dars->series->category?->name_bn,
                'image' => null,
                'media' => $this->darsItem($dars),
                'published_at' => $dars->created_at?->toIso8601String(),
                'updated_at' => $dars->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function darsItem(Dars $dars): array
    {
        $duration = $dars->duration_seconds ?? (int) round((float) $dars->segments_max_end_time) ?: null;

        return [
            'number' => $dars->number,
            'title_bn' => $dars->title_bn,
            'title_ar' => $dars->title_ar,
            'media_type' => $dars->media_type->value,
            'media_url' => $dars->media_url,
            'duration_seconds' => $duration,
            'created_at' => $dars->created_at?->toIso8601String(),
            'updated_at' => $dars->updated_at?->toIso8601String(),
        ];
    }

    private function summary(?string $markdown): ?string
    {
        return $markdown ? mb_substr(MarkdownText::toPlain($markdown), 0, 600) : null;
    }
}
