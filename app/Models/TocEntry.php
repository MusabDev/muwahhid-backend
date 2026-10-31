<?php

namespace App\Models;

use App\Models\Concerns\RefreshesSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['book_id', 'parent_id', 'position', 'title_ar', 'title_bn', 'page_number', 'sentence_id'])]
class TocEntry extends Model
{
    use RefreshesSeo;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'position' => 'integer',
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
     * @return HasMany<TocEntry, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(TocEntry::class, 'parent_id')->orderBy('position');
    }
}
