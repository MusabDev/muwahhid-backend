<?php

namespace App\Models;

use App\Enums\Lang;
use App\Support\ArabicNormalizer;
use App\Support\MarkdownText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['book_id', 'page_id', 'lang', 'marker', 'text_md'])]
#[Hidden(['text_normalized'])]
class BookFootnote extends Model
{
    protected static function booted(): void
    {
        static::saving(function (BookFootnote $footnote) {
            $footnote->forceFill(static::derivedText($footnote->text_md));
        });
    }

    /**
     * @return array{text_plain: string, text_normalized: string}
     */
    public static function derivedText(string $markdown): array
    {
        $plain = MarkdownText::toPlain($markdown);

        return [
            'text_plain' => $plain,
            'text_normalized' => ArabicNormalizer::normalize($plain),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lang' => Lang::class,
        ];
    }

    /**
     * @return BelongsTo<BookPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(BookPage::class, 'page_id');
    }
}
