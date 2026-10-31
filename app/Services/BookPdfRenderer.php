<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Enums\ExportVariant;
use App\Enums\Lang;
use App\Models\Book;
use App\Models\BookFootnote;
use App\Models\BookSentence;
use App\Support\MarkdownText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BookPdfRenderer
{
    /**
     * Classic cloth-binding colours (hue, chroma, lightness) for generated covers.
     * Mirrors BINDINGS in the web app's book-cover.tsx so the PDF wears the same cover.
     *
     * @var list<array{int, float, float}>
     */
    private const BINDINGS = [
        [150, 0.055, 0.31],
        [25, 0.085, 0.31],
        [255, 0.06, 0.29],
        [50, 0.05, 0.28],
        [190, 0.045, 0.31],
        [0, 0.07, 0.29],
        [90, 0.02, 0.22],
    ];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const BANGLA_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /**
     * Render the book to PDF bytes through Gotenberg (headless Chromium).
     *
     * Every PDF page is drawn by the template itself (running head, page tab,
     * footnotes), so Chromium prints with no margins and no header/footer.
     */
    public function render(Book $book, ExportVariant $variant, string $arabicFont, string $banglaFont): string
    {
        $book->load(['author', 'explainer', 'category', 'tocEntries' => fn ($query) => $query->whereNull('parent_id')->with('children.children'), 'pages.sentences', 'pages.footnotes']);

        $html = view('exports.book', [
            'book' => $book,
            'variant' => $variant,
            'languages' => $variant === ExportVariant::Bilingual ? [Lang::Bangla, Lang::Arabic] : [Lang::from($variant->value)],
            'fontCss' => $this->fontCss($arabicFont, $banglaFont),
            'cover' => $this->cover($book),
            'bookUrl' => config('app.frontend_url').'/books/'.$book->slug,
            'pages' => $book->pages->map(fn ($page) => [
                'number' => $page->number,
                'ar' => $this->blocks($page->sentences, Lang::Arabic),
                'bn' => $this->blocks($page->sentences, Lang::Bangla),
                'footnotes_ar' => $this->footnotes($page->footnotes->where('lang', Lang::Arabic), Lang::Arabic),
                'footnotes_bn' => $this->footnotes($page->footnotes->where('lang', Lang::Bangla), Lang::Bangla),
            ]),
        ])->render();

        $config = config('reader.pdf');

        $response = Http::timeout(config('services.gotenberg.timeout'))
            ->attach('files', $html, 'index.html')
            ->post(rtrim(config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                'paperWidth' => $config['paper_width'],
                'paperHeight' => $config['paper_height'],
                'marginTop' => '0',
                'marginBottom' => '0',
                'marginLeft' => '0',
                'marginRight' => '0',
                'printBackground' => 'true',
                'preferCssPageSize' => 'true',
                'waitForExpression' => 'window.pdfReady === true',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gotenberg failed ('.$response->status().'): '.mb_substr($response->body(), 0, 500));
        }

        return $response->body();
    }

    /**
     * Group a page's sentences into blocks (paragraphs, headings, quotes, list items, poetry lines).
     *
     * @param  Collection<int, BookSentence>  $sentences
     * @return list<array{type: string, html: string}>
     */
    public function blocks(Collection $sentences, Lang $lang): array
    {
        $blocks = [];
        $column = $lang === Lang::Arabic ? 'text_ar_md' : 'text_bn_md';

        foreach ($sentences as $sentence) {
            $type = $sentence->block_type->value;
            $html = $this->localizeFootnoteRefs(MarkdownText::toInlineHtml($sentence->{$column}), $lang);
            $last = array_key_last($blocks);
            $canJoin = $last !== null
                && ! $sentence->paragraph_break
                && $blocks[$last]['type'] === $type
                && in_array($sentence->block_type, [BlockType::Paragraph, BlockType::Quote], true);

            if ($canJoin) {
                $blocks[$last]['html'] .= ' '.$html;
            } elseif (trim(strip_tags($html)) !== '') {
                $blocks[] = ['type' => $type, 'html' => $html];
            }
        }

        return $blocks;
    }

    /**
     * Write a number with Arabic-Indic or Bengali digits, as the reader does.
     */
    public static function localizeDigits(int|string $value, Lang $lang): string
    {
        return str_replace(range(0, 9), $lang === Lang::Arabic ? self::ARABIC_DIGITS : self::BANGLA_DIGITS, (string) $value);
    }

    /**
     * Build @font-face rules: embed the font from resources/fonts when present,
     * otherwise import it from Google Fonts. The cover always uses Amiri and
     * Noto Serif Bengali, like the web app's covers.
     */
    public function fontCss(string $arabicFont, string $banglaFont): string
    {
        $css = '';
        $families = [];
        $keys = ['ar' => [$arabicFont, 'amiri'], 'bn' => [$banglaFont, 'noto-serif-bengali']];

        foreach ($keys as $lang => $langKeys) {
            foreach (array_unique($langKeys) as $index => $key) {
                $font = config("reader.fonts.{$lang}.{$key}") ?? config("reader.fonts.{$lang}.".config("reader.default_fonts.{$lang}"));
                $path = resource_path('fonts/'.$font['file']);

                if (is_file($path)) {
                    $format = str_ends_with($path, '.otf') ? 'opentype' : (str_ends_with($path, '.woff2') ? 'woff2' : 'truetype');
                    $css .= sprintf(
                        "@font-face{font-family:'%s';src:url(data:font/%s;base64,%s) format('%s');}\n",
                        $font['family'],
                        pathinfo($path, PATHINFO_EXTENSION),
                        base64_encode(file_get_contents($path)),
                        $format,
                    );
                } elseif ($font['google']) {
                    $css = "@import url('https://fonts.googleapis.com/css2?family={$font['google']}&display=block');\n".$css;
                }

                if ($index === 0) {
                    $families[$lang] = $font['family'];
                }
            }
        }

        return $css.sprintf(
            ":root{--font-ar:'%s','Amiri',serif;--font-bn:'%s','Noto Serif Bengali',serif;}\n",
            $families['ar'],
            $families['bn'],
        );
    }

    /**
     * Everything the cover template needs: the uploaded cover image inlined as a
     * data URI, or the colours and title sizes of the generated cloth cover.
     *
     * @return array{image: ?string, background: string, cloth: string, pattern: string, arabic_size: string, bangla_size: string}
     */
    public function cover(Book $book): array
    {
        $image = null;

        if ($book->cover_path && Storage::disk('public')->exists($book->cover_path)) {
            $disk = Storage::disk('public');
            $image = 'data:'.$disk->mimeType($book->cover_path).';base64,'.base64_encode($disk->get($book->cover_path));
        }

        return [
            'image' => $image,
            ...$this->cloth($book->title_ar.$book->title_bn),
            'pattern' => $this->latticeUri(),
            'arabic_size' => match (true) {
                mb_strlen($book->title_ar) <= 10 => '14cqw',
                mb_strlen($book->title_ar) <= 18 => '11.5cqw',
                mb_strlen($book->title_ar) <= 30 => '9.5cqw',
                default => '8cqw',
            },
            'bangla_size' => match (true) {
                mb_strlen($book->title_bn) <= 14 => '8cqw',
                mb_strlen($book->title_bn) <= 26 => '6.8cqw',
                default => '5.8cqw',
            },
        ];
    }

    /**
     * The cloth colour a title is bound in, picked by hash as on the web covers.
     *
     * @return array{background: string, cloth: string}
     */
    public function cloth(string $seed): array
    {
        [$hue, $chroma, $lightness] = self::BINDINGS[$this->hashString($seed) % count(self::BINDINGS)];

        return [
            'background' => sprintf(
                'linear-gradient(180deg, oklch(%s %s %s), oklch(%s %s %s))',
                $lightness + 0.025, $chroma, $hue,
                $lightness - 0.03, $chroma, $hue,
            ),
            'cloth' => sprintf('oklch(%s %s %s)', $lightness, $chroma, $hue),
        ];
    }

    /**
     * The gold rosette lattice as a data URI image.
     */
    public function latticeUri(int $columns = 5, int $rows = 7): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->goldLattice($columns, $rows));
    }

    /**
     * The web covers tint the rosette mask with a luminance mask, which print Chromium
     * ignores; recolour the mask's white lines gold on a transparent tile instead. The
     * tile is laid out as an odd grid in one image (centred like a repeating background)
     * because PDF tiling patterns are drawn at the wrong scale by some viewers.
     */
    private function goldLattice(int $columns = 5, int $rows = 7): string
    {
        $tile = file_get_contents(resource_path('images/rosette-mask.svg'));
        $tile = preg_replace(['/^<svg[^>]*>/', '/<\/svg>\s*$/', '/<rect[^>]*\/>/'], '', trim($tile)) ?? $tile;
        $tile = str_replace(['fill="#000000"', 'stroke="#ffffff"'], ['fill="none"', 'stroke="#cdad66"'], $tile);

        $uses = '';
        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $uses .= sprintf('<use href="#t" x="%d" y="%d"/>', $column * 100, $row * 100);
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d"><defs><g id="t">%3$s</g></defs>%4$s</svg>',
            $columns * 100, $rows * 100, $tile, $uses,
        );
    }

    /**
     * @param  Collection<int, BookFootnote>  $footnotes
     * @return list<array{marker: string, html: string}>
     */
    private function footnotes(Collection $footnotes, Lang $lang): array
    {
        return $footnotes->values()->map(fn (BookFootnote $footnote) => [
            'marker' => self::localizeDigits($footnote->marker, $lang),
            'html' => MarkdownText::toInlineHtml($footnote->text_md),
        ])->all();
    }

    private function localizeFootnoteRefs(string $html, Lang $lang): string
    {
        return preg_replace_callback(
            '/<sup class="fn-ref">([^<]+)<\/sup>/u',
            fn (array $match) => '<sup class="fn-ref">'.self::localizeDigits($match[1], $lang).'</sup>',
            $html,
        ) ?? $html;
    }

    /**
     * Port of the web app's hashString(): a Java-style 32-bit string hash over UTF-16 code units.
     */
    private function hashString(string $text): int
    {
        $hash = 0;

        foreach (unpack('n*', mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')) as $unit) {
            $hash = ((($hash * 31 + $unit) & 0xFFFFFFFF) ^ 0x80000000) - 0x80000000;
        }

        return abs($hash);
    }
}
