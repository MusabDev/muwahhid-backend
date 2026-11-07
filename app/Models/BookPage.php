<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Enums\Lang;
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
     * Whether this page ends mid-paragraph: its last paragraph or quote carries on as the
     * first sentence of the next page. Such pages are set to fill the sheet, like a printed
     * book, instead of leaving the space under their last line empty.
     */
    public function continuesInto(?BookPage $next): bool
    {
        $last = $this->sentences->last();
        $first = $next?->sentences->first();

        if ($last === null || $first === null || $first->paragraph_break) {
            return false;
        }

        return $first->block_type === $last->block_type
            && in_array($last->block_type, [BlockType::Paragraph, BlockType::Quote], true);
    }

    /**
     * Whether the page's last sentence stops mid-sentence in the given language: no sentence-final
     * mark (optionally followed by closing quotes or brackets) at its end. Only then is the page's
     * last line stretched to the full width, so a paragraph that really ends there isn't.
     */
    public function endsMidSentence(Lang $lang): bool
    {
        $last = $this->sentences->last();

        if ($last === null) {
            return false;
        }

        $text = trim($lang === Lang::Arabic ? $last->text_ar_plain : $last->text_bn_plain);

        return $text !== '' && preg_match('/[।॥.?!؟۔…]["”’»)\]]*$/u', $text) !== 1;
    }

    /**
     * @return HasMany<BookFootnote, $this>
     */
    public function footnotes(): HasMany
    {
        return $this->hasMany(BookFootnote::class, 'page_id')->orderBy('id');
    }
}
