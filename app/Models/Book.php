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
use Illuminate\Support\Facades\Storage;

#[Fillable(['slug', 'author_id', 'title_ar', 'title_bn', 'title_translit', 'about_md', 'is_published'])]
#[Hidden(['title_ar_normalized'])]
class Book extends Model
{
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
}
