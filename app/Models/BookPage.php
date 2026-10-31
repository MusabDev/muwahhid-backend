<?php

namespace App\Models;

use App\Models\Concerns\RefreshesSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['book_id', 'number'])]
class BookPage extends Model
{
    use RefreshesSeo;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return HasMany<BookSentence, $this>
     */
    public function sentences(): HasMany
    {
        return $this->hasMany(BookSentence::class, 'page_id')->orderBy('position');
    }

    /**
     * @return HasMany<BookFootnote, $this>
     */
    public function footnotes(): HasMany
    {
        return $this->hasMany(BookFootnote::class, 'page_id')->orderBy('id');
    }
}
