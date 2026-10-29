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

#[Fillable(['slug', 'author_id', 'book_id', 'title_ar', 'title_bn', 'title_translit', 'about_md', 'is_published'])]
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
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
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
