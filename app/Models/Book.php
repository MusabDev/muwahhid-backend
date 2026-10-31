<?php

namespace App\Models;

use App\Models\Concerns\RefreshesSeo;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['slug', 'author_id', 'category_id', 'is_sharh', 'explainer_id', 'title_ar', 'title_bn', 'title_translit', 'about_md', 'is_published'])]
#[Hidden(['title_ar_normalized'])]
class Book extends Model
{
    use RefreshesSeo;

    protected static function booted(): void
    {
        static::saving(function (Book $book) {
            $book->title_ar_normalized = ArabicNormalizer::normalize($book->title_ar);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_sharh' => 'boolean',
            'content_version' => 'integer',
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
     * and the names of the author and, for a sharh, the explainer.
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $pattern = '%'.addcslashes(ArabicNormalizer::normalize($term), '%_\\').'%';
        $names = fn (Builder $author) => $author->whereRaw("(name_ar_normalized || ' ' || name_bn || ' ' || name_translit) ILIKE ?", [$pattern]);

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("(title_ar_normalized || ' ' || title_bn || ' ' || title_translit) ILIKE ?", [$pattern])
            ->orWhereHas('author', $names)
            ->orWhereHas('explainer', $names));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /**
     * Mark the content as changed so cached PDF exports are regenerated.
     */
    public function bumpContentVersion(): void
    {
        $this->increment('content_version');
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
     * The scholar who wrote the explanation, when this book is a sharh.
     * `author` remains the author of the original text (matn).
     *
     * @return BelongsTo<Author, $this>
     */
    public function explainer(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'explainer_id');
    }

    /**
     * @return HasMany<BookPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(BookPage::class)->orderBy('number');
    }

    /**
     * @return HasMany<BookSentence, $this>
     */
    public function sentences(): HasMany
    {
        return $this->hasMany(BookSentence::class);
    }

    /**
     * @return HasMany<BookFootnote, $this>
     */
    public function footnotes(): HasMany
    {
        return $this->hasMany(BookFootnote::class);
    }

    /**
     * @return HasMany<TocEntry, $this>
     */
    public function tocEntries(): HasMany
    {
        return $this->hasMany(TocEntry::class)->orderBy('position');
    }

    /**
     * @return HasMany<BookExport, $this>
     */
    public function exports(): HasMany
    {
        return $this->hasMany(BookExport::class);
    }

    /**
     * @return HasMany<DarsSeries, $this>
     */
    public function darsSeries(): HasMany
    {
        return $this->hasMany(DarsSeries::class);
    }

    /**
     * Public pages of a published book, for IndexNow.
     *
     * @return list<string>
     */
    public function seoPaths(): array
    {
        return $this->is_published ? ['/books/'.$this->slug] : [];
    }
}
