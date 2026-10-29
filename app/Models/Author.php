<?php

namespace App\Models;

use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_ar', 'name_bn', 'name_translit', 'about_md'])]
#[Hidden(['name_ar_normalized'])]
class Author extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Author $author) {
            $author->name_ar_normalized = ArabicNormalizer::normalize($author->name_ar);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * @return HasMany<DarsSeries, $this>
     */
    public function darsSeries(): HasMany
    {
        return $this->hasMany(DarsSeries::class);
    }
}
