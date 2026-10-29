<?php

namespace App\Models;

use App\Enums\ExportStatus;
use App\Enums\ExportVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['book_id', 'variant', 'version', 'font_ar', 'font_bn', 'file_path', 'status', 'error'])]
class BookExport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant' => ExportVariant::class,
            'status' => ExportStatus::class,
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
