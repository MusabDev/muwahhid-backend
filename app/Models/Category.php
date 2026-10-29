<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject area (e.g. Aqidah, Hadith, Fiqh) shared by books and dars series.
 */
#[Fillable(['slug', 'name_bn', 'name_ar', 'position'])]
class Category extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
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
