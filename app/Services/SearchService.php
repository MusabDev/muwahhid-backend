<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookFootnote;
use App\Models\BookSentence;
use App\Models\DarsSegment;
use App\Models\DarsSeries;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Search titles, authors, sentences and transcripts across the whole site.
     *
     * @return array{books: Collection<int, Book>, authors: Collection<int, Author>, dars_series: Collection<int, DarsSeries>, sentences: Collection<int, array<string, mixed>>, segments: Collection<int, array<string, mixed>>}
     */
    public function global(string $query, int $limit = 20): array
    {
        $pattern = $this->likePattern($query);

        return [
            'books' => Book::published()
                ->with('author')
                ->whereRaw("(title_ar_normalized || ' ' || title_bn || ' ' || title_translit) ILIKE ?", [$pattern])
                ->limit($limit)
                ->get(),
            'authors' => Author::query()
                ->whereRaw("(name_ar_normalized || ' ' || name_bn || ' ' || name_translit) ILIKE ?", [$pattern])
                ->limit($limit)
                ->get(),
            'dars_series' => DarsSeries::published()
                ->whereRaw("(title_ar_normalized || ' ' || title_bn || ' ' || title_translit) ILIKE ?", [$pattern])
                ->limit($limit)
                ->get(),
            'sentences' => $this->sentences($query, $limit),
            'segments' => $this->segments($query, $limit),
        ];
    }

    /**
     * Search the sentences (and optionally footnotes) of published books, or a single book.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sentences(string $query, int $limit = 50, ?Book $book = null): Collection
    {
        $pattern = $this->likePattern($query);

        return BookSentence::query()
            ->select('book_sentences.*', 'book_pages.number as page_number', 'books.slug as book_slug', 'books.title_bn as book_title_bn', 'books.title_ar as book_title_ar')
            ->join('book_pages', 'book_pages.id', '=', 'book_sentences.page_id')
            ->join('books', 'books.id', '=', 'book_sentences.book_id')
            ->when($book, fn (Builder $q) => $q->where('book_sentences.book_id', $book->id), fn (Builder $q) => $q->where('books.is_published', true))
            ->where(fn (Builder $q) => $q
                ->where('book_sentences.text_ar_normalized', 'ILIKE', $pattern)
                ->orWhere('book_sentences.text_bn_plain', 'ILIKE', $pattern)
                ->orWhereRaw(
                    "(to_tsvector('simple', book_sentences.text_ar_normalized) || to_tsvector('simple', book_sentences.text_bn_plain)) @@ plainto_tsquery('simple', ?)",
                    [ArabicNormalizer::normalize($query)],
                ))
            ->orderBy('book_sentences.book_id')
            ->orderBy('book_pages.number')
            ->orderBy('book_sentences.position')
            ->limit($limit)
            ->get()
            ->map(fn (BookSentence $sentence) => [
                'type' => 'sentence',
                'sentence_id' => $sentence->id,
                'book_id' => $sentence->book_id,
                'book_slug' => $sentence->book_slug,
                'book_title_ar' => $sentence->book_title_ar,
                'book_title_bn' => $sentence->book_title_bn,
                'page_number' => $sentence->page_number,
                'lang' => str_contains($sentence->text_ar_normalized, ArabicNormalizer::normalize($query)) ? 'ar' : 'bn',
                'text_ar' => $sentence->text_ar_plain,
                'text_bn' => $sentence->text_bn_plain,
            ]);
    }

    /**
     * Search the footnotes of a single book.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function footnotes(string $query, Book $book, int $limit = 50): Collection
    {
        return BookFootnote::query()
            ->select('book_footnotes.*', 'book_pages.number as page_number')
            ->join('book_pages', 'book_pages.id', '=', 'book_footnotes.page_id')
            ->where('book_footnotes.book_id', $book->id)
            ->where('book_footnotes.text_normalized', 'ILIKE', $this->likePattern($query))
            ->orderBy('book_pages.number')
            ->limit($limit)
            ->get()
            ->map(fn (BookFootnote $footnote) => [
                'type' => 'footnote',
                'footnote_id' => $footnote->id,
                'page_number' => $footnote->page_number,
                'lang' => $footnote->lang,
                'marker' => $footnote->marker,
                'text' => $footnote->text_plain,
            ]);
    }

    /**
     * Search the transcripts of published dars series.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function segments(string $query, int $limit = 20): Collection
    {
        $pattern = $this->likePattern($query);

        return DarsSegment::query()
            ->select('dars_segments.*', 'durus.number as dars_number', 'durus.title_bn as dars_title_bn', 'dars_series.slug as series_slug')
            ->join('durus', 'durus.id', '=', 'dars_segments.dars_id')
            ->join('dars_series', 'dars_series.id', '=', 'durus.series_id')
            ->where('dars_series.is_published', true)
            ->where(fn (Builder $q) => $q
                ->where('dars_segments.text_ar_normalized', 'ILIKE', $pattern)
                ->orWhere('dars_segments.text_bn', 'ILIKE', $pattern))
            ->orderBy('dars_segments.dars_id')
            ->orderBy('dars_segments.position')
            ->limit($limit)
            ->get()
            ->map(fn (DarsSegment $segment) => [
                'type' => 'segment',
                'segment_id' => $segment->id,
                'dars_id' => $segment->dars_id,
                'dars_number' => $segment->dars_number,
                'dars_title_bn' => $segment->dars_title_bn,
                'series_slug' => $segment->series_slug,
                'start_time' => $segment->start_time,
                'text_ar' => $segment->text_ar,
                'text_bn' => $segment->text_bn,
            ]);
    }

    private function likePattern(string $query): string
    {
        $normalized = ArabicNormalizer::normalize($query);

        return '%'.addcslashes($normalized, '%_\\').'%';
    }
}
