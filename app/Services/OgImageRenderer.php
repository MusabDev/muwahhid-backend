<?php

namespace App\Services;

use App\Enums\Lang;
use App\Models\Author;
use App\Models\Book;
use App\Models\Dars;
use App\Models\DarsSeries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * 1200×630 JPEG share cards (og:image) for books, authors, dars series and durus.
 *
 * Drawn by headless Chromium through Gotenberg, like the PDFs, because Arabic joining and
 * Bangla conjuncts need a real text shaper. Each card is cached on the public disk under a
 * hash of what it shows, so an edit produces a new file and old ones are cleaned up.
 */
class OgImageRenderer
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function __construct(private BookPdfRenderer $covers) {}

    /**
     * Path on the public disk of the card for this item, rendering it first if needed.
     */
    public function path(Book|Author|DarsSeries|Dars|null $item): string
    {
        $card = $this->card($item);
        $type = $item ? strtolower(class_basename($item)) : 'site';
        $key = $item?->getKey() ?? 'default';
        $hash = substr(md5(json_encode($card)), 0, 12);
        $path = "og/{$type}/{$key}-{$hash}.jpg";
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        // one render per card even when several crawlers ask at once
        return Cache::lock("og:{$path}", 60)->block(45, function () use ($disk, $path, $card, $type, $key) {
            if ($disk->exists($path)) {
                return $path;
            }

            $image = $this->render($card);
            // drop cards for older versions of this item
            $disk->delete(array_filter($disk->files("og/{$type}"), fn (string $file) => str_starts_with(basename($file), "{$key}-")));
            $disk->put($path, $image);

            return $path;
        });
    }

    /**
     * What the card shows for an item.
     *
     * @return array{kicker: ?string, titleAr: ?string, titleBn: string, subtitle: ?string, seed: string, image: ?string}
     */
    public function card(Book|Author|DarsSeries|Dars|null $item): array
    {
        return match (true) {
            $item instanceof Book => [
                'kicker' => $item->is_sharh && $item->explainer ? 'শারহ · আরবি ও বাংলা' : 'কিতাব · আরবি ও বাংলা',
                'titleAr' => $item->title_ar,
                'titleBn' => $item->title_bn,
                'subtitle' => collect([$item->author?->name_bn, $item->is_sharh ? $item->explainer?->name_bn : null])->filter()->join(' · '),
                'seed' => $item->title_ar.$item->title_bn,
                'image' => $item->cover_path,
            ],
            $item instanceof Author => [
                'kicker' => 'আহলুল ইলম',
                'titleAr' => $item->name_ar,
                'titleBn' => $item->name_bn,
                'subtitle' => $item->death_year_ah ? 'মৃত্যু '.BookPdfRenderer::localizeDigits($item->death_year_ah, Lang::Bangla).' হিজরি' : null,
                'seed' => $item->name_ar.$item->name_bn,
                'image' => null,
            ],
            $item instanceof DarsSeries => [
                'kicker' => 'দারস সিরিজ · আরবি ও বাংলা সাবটাইটেল',
                'titleAr' => $item->title_ar,
                'titleBn' => $item->title_bn,
                'subtitle' => $item->author?->name_bn,
                'seed' => $item->title_ar.$item->title_bn,
                'image' => $item->book?->cover_path,
            ],
            $item instanceof Dars => [
                'kicker' => 'দারস '.BookPdfRenderer::localizeDigits($item->number, Lang::Bangla).' · '.$item->series->title_bn,
                'titleAr' => $item->title_ar ?: $item->series->title_ar,
                'titleBn' => $item->title_bn,
                'subtitle' => $item->series->author?->name_bn,
                'seed' => $item->series->title_ar.$item->series->title_bn,
                'image' => null,
            ],
            default => [
                'kicker' => 'আহলুল ইলমের কিতাব ও দারস',
                'titleAr' => 'الموحِّد',
                'titleBn' => 'আল-মুওয়াহহিদ',
                'subtitle' => 'আরবি মূলপাঠ ও বাংলা অনুবাদ, বাক্য ধরে ধরে',
                'seed' => 'muwahhid',
                'image' => null,
            ],
        };
    }

    /**
     * @param  array{kicker: ?string, titleAr: ?string, titleBn: string, subtitle: ?string, seed: string, image: ?string}  $card
     */
    public function render(array $card): string
    {
        $disk = Storage::disk('public');
        $image = $card['image'] && $disk->exists($card['image'])
            ? 'data:'.$disk->mimeType($card['image']).';base64,'.base64_encode($disk->get($card['image']))
            : null;

        $html = view('og.card', [
            ...$card,
            'image' => $image,
            'cloth' => $this->covers->cloth($card['seed']),
            'pattern' => $this->covers->latticeUri(),
            'fontCss' => $this->covers->fontCss('amiri', 'noto-serif-bengali'),
        ])->render();

        $response = Http::timeout(60)
            ->attach('files', $html, 'index.html')
            ->post(rtrim(config('services.gotenberg.url'), '/').'/forms/chromium/screenshot/html', [
                'width' => self::WIDTH,
                'height' => self::HEIGHT,
                'clip' => 'true',
                // JPEG keeps cards well under the ~300 KB that WhatsApp and others allow for previews
                'format' => 'jpeg',
                'quality' => 86,
                'optimizeForSpeed' => 'true',
                'waitForExpression' => 'window.ogReady === true',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gotenberg screenshot failed ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        return $response->body();
    }
}
