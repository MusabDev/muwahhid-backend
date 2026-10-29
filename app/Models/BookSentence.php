<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Support\ArabicNormalizer;
use App\Support\MarkdownText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['book_id', 'page_id', 'position', 'block_type', 'paragraph_break', 'text_ar_md', 'text_bn_md'])]
#[Hidden(['text_ar_normalized'])]
class BookSentence extends Model
{
    protected static function booted(): void
    {
        static::saving(function (BookSentence $sentence) {
            $sentence->forceFill(static::derivedText($sentence->text_ar_md, $sentence->text_bn_md));
        });
    }

    /**
     * Plain and normalized copies of the markdown, used for search and highlight offsets.
     *
     * @return array{text_ar_plain: string, text_bn_plain: string, text_ar_normalized: string}
     */
    public static function derivedText(string $arabicMarkdown, string $banglaMarkdown): array
    {
        $arabicPlain = MarkdownText::toPlain($arabicMarkdown);

        return [
            'text_ar_plain' => $arabicPlain,
            'text_bn_plain' => MarkdownText::toPlain($banglaMarkdown),
            'text_ar_normalized' => ArabicNormalizer::normalize($arabicPlain),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_type' => BlockType::class,
            'paragraph_break' => 'boolean',
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
     * @return BelongsTo<BookPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(BookPage::class, 'page_id');
    }

    /**
     * @return MorphMany<Annotation, $this>
     */
    public function annotations(): MorphMany
    {
        return $this->morphMany(Annotation::class, 'annotatable');
    }
}
