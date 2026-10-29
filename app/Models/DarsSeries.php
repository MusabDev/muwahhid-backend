<?php

namespace App\Models;

use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'author_id', 'book_id', 'category_id', 'title_ar', 'title_bn', 'title_translit', 'about_md', 'is_published'])]
#[Hidden(['title_ar_normalized'])]
class DarsSeries extends Model
{
    protected $table = 'dars_series';

    protected static function booted(): void
    {
        static::saving(function (DarsSeries $series) {
            $series->title_ar_normalized = ArabicNormalizer::normalize($series->title_ar);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Match a search term against the titles (Arabic normalized, Bangla, transliteration)
     * and the teacher's name.
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $pattern = '%'.addcslashes(ArabicNormalizer::normalize($term), '%_\\').'%';
        $names = fn (Builder $author) => $author->whereRaw("(name_ar_normalized || ' ' || name_bn || ' ' || name_translit) ILIKE ?", [$pattern]);

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("(title_ar_normalized || ' ' || title_bn || ' ' || title_translit) ILIKE ?", [$pattern])
            ->orWhereHas('author', $names));
    }

    /**
     * Add what a series card shows: dars count, total running time and the media type of the first dars.
     * A dars without a recorded duration counts up to the end of its transcript.
     */
    #[Scope]
    protected function withCardStats(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('dars_series.*');
        }

        $query->withCount('durus')->selectSub(
            'SELECT COALESCE(SUM(COALESCE(d.duration_seconds, (SELECT MAX(COALESCE(s.end_time, s.start_time)) FROM dars_segments s WHERE s.dars_id = d.id), 0)), 0)
             FROM durus d WHERE d.series_id = dars_series.id',
            'total_seconds',
        )->selectSub(
            'SELECT d.media_type FROM durus d WHERE d.series_id = dars_series.id ORDER BY d.number LIMIT 1',
            'first_media_type',
        );
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return HasMany<Dars, $this>
     */
    public function durus(): HasMany
    {
        return $this->hasMany(Dars::class, 'series_id')->orderBy('number');
    }
}
