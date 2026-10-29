<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Enums\ExportVariant;
use App\Enums\Lang;
use App\Models\Book;
use App\Models\BookSentence;
use App\Support\MarkdownText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BookPdfRenderer
{
    /**
     * Render the book to PDF bytes through Gotenberg (headless Chromium).
     */
    public function render(Book $book, ExportVariant $variant, string $arabicFont, string $banglaFont): string
    {
        $book->load(['author', 'explainer', 'tocEntries' => fn ($query) => $query->whereNull('parent_id')->with('children.children'), 'pages.sentences', 'pages.footnotes']);

        $fontCss = $this->fontCss($arabicFont, $banglaFont);
        $title = match ($variant) {
            ExportVariant::Arabic => $book->title_ar,
            ExportVariant::Bangla => $book->title_bn,
            ExportVariant::Bilingual => $book->title_ar.' — '.$book->title_bn,
        };

        $html = view('exports.book', [
            'book' => $book,
            'variant' => $variant,
            'fontCss' => $fontCss,
            'pages' => $book->pages->map(fn ($page) => [
                'number' => $page->number,
                'ar' => $this->blocks($page->sentences, Lang::Arabic),
                'bn' => $this->blocks($page->sentences, Lang::Bangla),
                'footnotes_ar' => $page->footnotes->where('lang', Lang::Arabic)->values(),
                'footnotes_bn' => $page->footnotes->where('lang', Lang::Bangla)->values(),
            ]),
        ])->render();

        $header = view('exports.header', ['fontCss' => $fontCss, 'title' => $title])->render();
        $footer = view('exports.footer', ['fontCss' => $fontCss])->render();

        $config = config('reader.pdf');

        $response = Http::timeout(config('services.gotenberg.timeout'))
            ->attach('files', $html, 'index.html')
            ->attach('files', $header, 'header.html')
            ->attach('files', $footer, 'footer.html')
            ->post(rtrim(config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                'paperWidth' => $config['paper_width'],
                'paperHeight' => $config['paper_height'],
                'marginTop' => $config['margin'],
                'marginBottom' => $config['margin'],
                'marginLeft' => $config['margin'],
                'marginRight' => $config['margin'],
                'printBackground' => 'true',
                'preferCssPageSize' => 'false',
                'waitDelay' => '1s',
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
            $html = MarkdownText::toInlineHtml($sentence->{$column});
            $last = array_key_last($blocks);
            $canJoin = $last !== null
                && ! $sentence->paragraph_break
                && $blocks[$last]['type'] === $type
                && in_array($sentence->block_type, [BlockType::Paragraph, BlockType::Quote], true);

            if ($canJoin) {
                $blocks[$last]['html'] .= ' '.$html;
            } else {
                $blocks[] = ['type' => $type, 'html' => $html];
            }
        }

        return $blocks;
    }

    /**
     * Build @font-face rules: embed the font from resources/fonts when present,
     * otherwise import it from Google Fonts.
     */
    public function fontCss(string $arabicFont, string $banglaFont): string
    {
        $css = '';
        $families = [];

        foreach (['ar' => $arabicFont, 'bn' => $banglaFont] as $lang => $key) {
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

            $families[$lang] = $font['family'];
        }

        return $css.sprintf(
            ":root{--font-ar:'%s','Amiri',serif;--font-bn:'%s','Noto Serif Bengali',serif;}\n",
            $families['ar'],
            $families['bn'],
        );
    }
}
