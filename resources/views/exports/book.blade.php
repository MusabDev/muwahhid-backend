@php
    use App\Enums\ExportVariant;
    use App\Enums\Lang;
    use App\Services\BookPdfRenderer;
    $tocLang = $variant === ExportVariant::Arabic ? Lang::Arabic : Lang::Bangla;
@endphp
<!doctype html>
<html lang="{{ $languages[0]->value }}">
<head>
<meta charset="utf-8">
<title>{{ $variant === ExportVariant::Arabic ? $book->title_ar : $book->title_bn }}</title>
<style>
{!! $fontCss !!}
@page { size: {{ config('reader.pdf.paper_width') }} {{ config('reader.pdf.paper_height') }}; margin: 0; }
:root {
    --pw: {{ config('reader.pdf.paper_width') }};
    --ph: {{ config('reader.pdf.paper_height') }};
    --gold: #cdad66;
    /* the web reader's "paper" theme */
    --page-bg: oklch(0.99 0.008 90);
    --page-ink: oklch(0.22 0.02 60);
    --page-muted: oklch(0.52 0.03 65);
    --page-accent: oklch(0.55 0.1 60);
    --page-hover: oklch(0.93 0.05 85);
    --text-bn: 11pt;
    --text-ar: calc(11pt * 1.22);
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: var(--page-bg); color: var(--page-ink); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
p { margin: 0; }
a { color: inherit; text-decoration: none; }

.sheet { position: relative; width: var(--pw); height: var(--ph); overflow: hidden; break-after: page; background: var(--page-bg); }
.sheet:last-child { break-after: auto; }

/* ---------- cover ---------- */
.cover { container-type: inline-size; color: var(--gold); }
.cover > * { position: absolute; }
.cover-image { inset: 0; width: 100%; height: 100%; object-fit: cover; }
.cover-spine-shade { inset-block: 0; left: 0; width: 5%; background: linear-gradient(to right, rgb(0 0 0 / .35), rgb(255 255 255 / .1), transparent); }
.cover-spine { inset-block: 0; left: 0; width: 9%; background: linear-gradient(to right, rgb(0 0 0 / .45), rgb(0 0 0 / .3), rgb(0 0 0 / .2)); }
.cover-spine-rule { inset-block: 0; left: 9%; width: 1px; opacity: .7; background: var(--gold); }
.cover-spine-fade { inset-block: 0; left: 9%; width: 3%; background: linear-gradient(to right, rgb(0 0 0 / .2), transparent); }
.cover-frame { top: 4.5%; right: 6%; bottom: 4.5%; left: 15%; border: 1px solid #cdad66b3; }
.cover-frame > div { position: absolute; inset: 2.2cqw; border: 1px solid #cdad664d; }
.cover-lattice { top: 6.5%; right: 8.5%; bottom: 6.5%; left: 17.5%; }
.cover-lattice > div { position: absolute; inset: 0; opacity: .16; background-size: calc(var(--pw) * .26 * 5) auto; background-position: center; background-repeat: no-repeat; }
/* print Chromium drops CSS masks, so the lattice fades out behind the titles under a cloth-coloured wash instead */
.cover-lattice > .cover-lattice-fade { opacity: 1; }
.cover-title-ar { top: 11%; bottom: 53%; right: 10%; left: 19%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 3.5cqw; text-align: center; }
.cover-star { width: 6.5cqw; flex-shrink: 0; }
.cover-title-ar p { font-family: 'Amiri', serif; font-weight: 700; line-height: 1.45; color: #e4c987; text-shadow: 0 1px 0 rgb(0 0 0 / .35); text-wrap: balance; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }
.cover-divider { top: 48.6%; left: 54.5%; width: 34cqw; transform: translateX(-50%); }
.cover-title-bn { top: 53.5%; right: 11%; left: 20%; text-align: center; }
.cover-title-bn p { font-family: 'Noto Serif Bengali', serif; font-weight: 600; line-height: 1.3; color: #f1e8d0; text-wrap: balance; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
.cover-author { right: 11%; left: 20%; bottom: 10.5%; text-align: center; font-family: 'Noto Serif Bengali', serif; }
.cover-author.is-sharh { bottom: 12.5%; }
.cover-author.ar { font-family: 'Amiri', serif; }
.cover-author.ar .cover-author-name { font-size: 6cqw; }
.cover-author.ar .cover-explainer { font-size: 4.8cqw; }
.cover-author-rule { display: block; width: 12cqw; height: 1px; margin: 0 auto 2.5cqw; background: #cdad6699; }
.cover-author-name { font-size: 5cqw; line-height: 1.375; color: rgb(232 220 192 / .8); text-wrap: balance; }
.is-sharh .cover-author-name { color: rgb(232 220 192 / .85); }
.cover-explainer { margin-top: 1cqw; font-size: 4cqw; line-height: 1.375; color: rgb(232 220 192 / .6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cover-explainer span { color: rgb(205 173 102 / .8); }

/* ---------- inside cover ---------- */
.colophon { display: flex; flex-direction: column; justify-content: space-between; padding: .85in .7in .6in; font-family: var(--font-bn); text-align: center; }
.colophon.ar { font-family: var(--font-ar); }
.colophon-book { display: flex; flex-direction: column; align-items: center; }
.colophon-star { width: .2in; color: var(--page-accent); margin-bottom: .14in; }
.colophon-title-ar { font-family: 'Amiri', serif; font-size: 22pt; font-weight: 700; line-height: 1.5; color: var(--page-ink); text-wrap: balance; }
.colophon-title-bn { margin-top: .04in; font-family: 'Noto Serif Bengali', serif; font-size: 15pt; font-weight: 600; line-height: 1.4; color: var(--page-ink); text-wrap: balance; }
.colophon-divider { width: 1.5in; margin: .2in 0 .22in; color: var(--page-accent); }
.colophon-facts { margin: 0; display: grid; gap: .07in; font-size: 9.5pt; line-height: 1.5; }
.colophon.ar .colophon-facts { font-size: 11.5pt; }
.colophon-facts > div { display: flex; justify-content: center; gap: .5em; }
.colophon-facts dt { color: var(--page-muted); }
.colophon-facts dt::after { content: ':'; }
.colophon-facts dd { margin: 0; color: var(--page-ink); font-weight: 600; }
.colophon-about { margin-top: .28in; max-width: 3.6in; padding: .14in .2in; border-block: 1px solid color-mix(in oklch, var(--page-accent) 30%, transparent); font-size: 9pt; line-height: 1.75; color: color-mix(in oklch, var(--page-ink) 80%, transparent); }
.colophon-about p + p { margin-top: .4em; }
.colophon-brand { display: flex; flex-direction: column; align-items: center; gap: .08in; }
.brand { display: flex; align-items: center; gap: .09in; color: var(--page-ink); }
.brand-mark { width: .3in; height: .3in; color: var(--gold); }
.brand-name { font-family: 'Noto Serif Bengali', serif; font-size: 13pt; font-weight: 700; letter-spacing: .02em; }
.colophon.ar .brand-name { font-family: 'Amiri', serif; font-size: 16pt; }
.colophon-link { display: flex; flex-direction: column; align-items: center; gap: .02in; margin-top: .04in; padding: .07in .2in; border: 1px solid color-mix(in oklch, var(--page-accent) 45%, transparent); border-radius: 3px; background: var(--page-hover); font-size: 8.5pt; color: var(--page-muted); }
.colophon.ar .colophon-link { font-size: 10pt; }
.colophon-url { font-family: 'Noto Serif Bengali', serif; font-size: 8.5pt; font-weight: 600; color: var(--page-accent); text-decoration: underline; text-underline-offset: 2px; }

/* ---------- table of contents ---------- */
.toc { padding: .7in .6in .6in; display: flex; flex-direction: column; font-family: var(--font-bn); }
.toc.ar { font-family: var(--font-ar); }
.toc-head { display: flex; flex-direction: column; align-items: center; gap: .09in; margin-bottom: .32in; color: var(--page-accent); }
.toc-head .toc-star { width: .2in; }
.toc-head h2 { margin: 0; font-size: 19pt; font-weight: 700; line-height: 1.3; color: var(--page-ink); }
.toc.ar .toc-head h2 { font-size: 23pt; }
.toc-head .toc-sub { font-size: 8.5pt; letter-spacing: .04em; color: var(--page-muted); }
.toc-head .toc-divider { width: 1.6in; }
.toc-continued { text-align: center; font-size: 8.5pt; color: var(--page-muted); margin-bottom: .22in; padding-bottom: .08in; border-bottom: 1px solid color-mix(in oklch, var(--page-accent) 30%, transparent); }
.toc-rows { flex: 1; }
.toc-row { display: block; break-inside: avoid; }
.toc-line { display: flex; align-items: baseline; gap: .07in; }
.toc-title { min-width: 0; }
.toc-leader { flex: 1; min-width: .25in; border-bottom: 1.2px dotted color-mix(in oklch, var(--page-accent) 55%, transparent); transform: translateY(-.25em); }
.toc-page { font-variant-numeric: tabular-nums; color: var(--page-accent); min-width: .22in; text-align: end; }
.toc-title-ar { display: block; margin-top: -.15em; font-family: var(--font-ar); font-size: 9.5pt; font-weight: 400; line-height: 1.55; color: var(--page-muted); text-align: left; }
.toc-row.depth-0 { margin-top: .13in; font-size: 10.5pt; line-height: 1.6; font-weight: 700; }
.toc-row.depth-0:first-child { margin-top: 0; }
.toc-row.depth-0 .toc-title::before { content: '◆'; color: var(--page-accent); font-size: .6em; vertical-align: .2em; margin-inline-end: .55em; }
.toc-row.depth-1 { margin-top: .045in; padding-inline-start: .22in; font-size: 9.5pt; line-height: 1.6; }
.toc-row.depth-2 { margin-top: .03in; padding-inline-start: .44in; font-size: 8.8pt; line-height: 1.55; color: var(--page-muted); }
.toc-row.depth-1 .toc-title-ar, .toc-row.depth-2 .toc-title-ar { font-size: 8.8pt; }
.toc.ar .toc-row.depth-0 { font-size: 12.5pt; }
.toc.ar .toc-row.depth-1 { font-size: 11.5pt; }
.toc.ar .toc-row.depth-2 { font-size: 10.5pt; }

/* ---------- book pages (mirrors the web reader's PageSheet) ---------- */
.page { --fit: 1; display: flex; flex-direction: column; padding: 0 .6in .55in; }
.running-head { display: flex; align-items: flex-end; gap: .13in; margin-bottom: .2in; }
.running-title { flex: 1; min-width: 0; padding-bottom: 3px; border-bottom: 1px solid color-mix(in oklch, var(--page-accent) 45%, transparent); color: var(--page-muted); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bn .running-title { font-family: var(--font-bn); font-size: 8.5pt; }
.ar .running-title { font-family: var(--font-ar); font-size: 10pt; }
.page-tab { position: relative; flex-shrink: 0; width: .42in; height: .6in; display: grid; place-items: center; padding-bottom: .1in; }
.page-tab svg { position: absolute; inset: 0; width: 100%; height: 100%; color: var(--page-accent); }
.page-tab span { position: relative; color: var(--page-accent); font-variant-numeric: tabular-nums; }
.bn .page-tab span { font-family: var(--font-bn); font-size: 9pt; font-weight: 600; }
.ar .page-tab span { font-family: var(--font-ar); font-size: 10.5pt; }

.text { flex: 1 0 auto; }
.text.bn { font-family: var(--font-bn); font-size: calc(var(--text-bn) * var(--fit)); line-height: 1.8; text-align: justify; }
.text.ar { font-family: var(--font-ar); font-size: calc(var(--text-ar) * var(--fit)); line-height: 2; text-align: justify; direction: rtl; }
.text p { margin-bottom: .5em; }
.text p:last-child { margin-bottom: 0; }
.text h3 { margin: .9em 0; text-align: center; font-size: 1.2em; line-height: 1.375; font-weight: 700; }
.text.ar h3 { font-size: 1.25em; }
.text h3:first-child { margin-top: .2em; }
.text blockquote { margin: .7em 0; padding-inline-start: 1em; border-inline-start: 3px solid color-mix(in oklch, var(--page-accent) 50%, transparent); }
.text .li { position: relative; margin: .35em 0; padding-inline-start: 1.5em; }
.text .li::before { content: '◆'; position: absolute; inset-inline-start: .25em; top: 0; font-size: .7em; line-height: inherit; color: var(--page-ink); }
.text .poetry { margin: .25em 0; text-align: center; font-style: italic; }
.text strong { font-weight: 700; }
.fn-ref { font-size: .68em; vertical-align: super; line-height: 0; color: var(--page-accent); font-weight: 600; padding: 0 .15em; }

/* footnotes: pinned to the foot of the page under a short print-style rule */
.footnotes { margin-top: .16in; color: color-mix(in oklch, var(--page-ink) 80%, transparent); }
.footnotes.bn { font-family: var(--font-bn); font-size: calc(var(--text-bn) * var(--fit) * 6 / 7); line-height: 1.75; text-align: justify; }
.footnotes.ar { font-family: var(--font-ar); font-size: calc(var(--text-ar) * var(--fit) * 6 / 7); line-height: 1.85; text-align: justify; direction: rtl; }
.footnotes-rule { width: 33%; max-width: 1.75in; height: 1px; margin-bottom: .09in; background: color-mix(in oklch, var(--page-accent) 50%, transparent); }
.footnote { display: flex; gap: .3em; }
.footnote + .footnote { margin-top: .35em; }
.footnote-marker { flex-shrink: 0; font-weight: 600; color: var(--page-accent); font-variant-numeric: tabular-nums; }
.footnote-text { flex: 1; min-width: 0; }
</style>
</head>
<body>

@include('exports.cover', ['book' => $book, 'cover' => $cover, 'variant' => $variant])
@include('exports.colophon', ['book' => $book, 'lang' => $tocLang, 'bookUrl' => $bookUrl])

@if ($book->tocEntries->isNotEmpty())
    <section class="sheet toc {{ $tocLang->value }}" dir="{{ $tocLang === Lang::Arabic ? 'rtl' : 'ltr' }}" id="toc">
        <header class="toc-head">
            @include('exports.ornament', ['shape' => 'star', 'class' => 'toc-star'])
            <h2>{{ $tocLang === Lang::Arabic ? 'الفهرس' : 'সূচিপত্র' }}</h2>
            <span class="toc-sub">{{ $tocLang === Lang::Arabic ? $book->title_ar : $book->title_bn }}</span>
            @include('exports.ornament', ['shape' => 'divider', 'class' => 'toc-divider'])
        </header>
        <div class="toc-rows">
            @include('exports.toc', ['entries' => $book->tocEntries, 'variant' => $variant, 'depth' => 0])
        </div>
    </section>
@endif

<div id="content">
@foreach ($pages as $page)
    <div class="page-group">
    @foreach ($languages as $lang)
        @php($isArabic = $lang === Lang::Arabic)
        <section class="sheet page {{ $lang->value }}" lang="{{ $lang->value }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}" id="p-{{ $page['number'] }}-{{ $lang->value }}">
            {{-- running head: title at the reading start, page number in a bookmark tab hanging from the top edge --}}
            <header class="running-head">
                <span class="running-title">{{ $isArabic ? $book->title_ar : $book->title_bn }}</span>
                <span class="page-tab">
                    <svg viewBox="0 0 44 64" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M3 0 V50 L22 62 L41 50 V0" fill="var(--page-hover)" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                    </svg>
                    <span>{{ BookPdfRenderer::localizeDigits($page['number'], $lang) }}</span>
                </span>
            </header>

            <div class="text {{ $lang->value }}">
                @foreach ($page[$lang->value] as $block)
                    @switch($block['type'])
                        @case('heading')
                            <h3>{!! $block['html'] !!}</h3>
                            @break
                        @case('quote')
                            <blockquote>{!! $block['html'] !!}</blockquote>
                            @break
                        @case('list_item')
                            <div class="li">{!! $block['html'] !!}</div>
                            @break
                        @case('poetry')
                            <div class="poetry">{!! $block['html'] !!}</div>
                            @break
                        @default
                            <p>{!! $block['html'] !!}</p>
                    @endswitch
                @endforeach
            </div>

            @if ($page['footnotes_'.$lang->value])
                <section class="footnotes {{ $lang->value }}">
                    <div class="footnotes-rule"></div>
                    @foreach ($page['footnotes_'.$lang->value] as $footnote)
                        <div class="footnote">
                            <span class="footnote-marker">({{ $footnote['marker'] }})</span>
                            <span class="footnote-text">{!! $footnote['html'] !!}</span>
                        </div>
                    @endforeach
                </section>
            @endif
        </section>
    @endforeach
    </div>
@endforeach
</div>

<script>
/*
 * Every .sheet is exactly one printed page. Before printing:
 * 1. spread the contents rows over as many sheets as they need;
 * 2. fit each book page on its sheet, shrinking the type a little first and
 *    carrying trailing blocks onto a continuation sheet only as a last resort,
 *    so footnotes always sit at the foot of a page;
 * 3. in bilingual exports, interleave the Bangla and Arabic sheets and pad with
 *    a blank page so Bangla is always the left page and Arabic the right, like
 *    an opened bilingual book (and like the web reader).
 */
(async () => {
    const BILINGUAL = @json($variant === ExportVariant::Bilingual);
    const MIN_FIT = 0.86;

    const overflows = (sheet) => sheet.scrollHeight > sheet.clientHeight + 1;
    const blankSheet = () => Object.assign(document.createElement('section'), { className: 'sheet blank' });

    try {
        await document.fonts.ready;
    } catch (e) {}

    const toc = document.getElementById('toc');
    if (toc) {
        let sheet = toc;
        while (overflows(sheet)) {
            const rows = sheet.querySelector('.toc-rows');
            const next = sheet.cloneNode(false);
            next.removeAttribute('id');
            const continued = document.createElement('div');
            continued.className = 'toc-continued';
            continued.textContent = toc.querySelector('.toc-head h2').textContent;
            const nextRows = document.createElement('div');
            nextRows.className = 'toc-rows';
            next.append(continued, nextRows);
            sheet.after(next);
            while (overflows(sheet) && rows.children.length > 1) {
                nextRows.prepend(rows.lastElementChild);
            }
            sheet = next;
        }
    }

    const fit = (sheet) => {
        let scale = 1;
        while (overflows(sheet) && scale > MIN_FIT) {
            scale = Math.round((scale - 0.02) * 100) / 100;
            sheet.style.setProperty('--fit', scale);
        }
        if (!overflows(sheet)) return [sheet];

        // too long even in smaller type: carry the last blocks over, footnotes go with them
        sheet.style.setProperty('--fit', 1);
        const text = sheet.querySelector('.text');
        const next = sheet.cloneNode(false);
        next.removeAttribute('id');
        next.style.setProperty('--fit', 1);
        const nextText = text.cloneNode(false);
        next.append(sheet.querySelector('.running-head').cloneNode(true), nextText);
        const footnotes = sheet.querySelector('.footnotes');
        if (footnotes) next.append(footnotes);
        sheet.after(next);
        while (overflows(sheet) && text.children.length > 1) {
            nextText.prepend(text.lastElementChild);
        }
        return [sheet, ...fit(next)];
    };

    for (const group of document.querySelectorAll('.page-group')) {
        const runs = [...group.querySelectorAll(':scope > .sheet')].map(fit);
        if (!BILINGUAL) continue;
        const [bn, ar] = runs;
        const spreads = Math.max(bn.length, ar.length);
        for (let i = 0; i < spreads; i++) {
            group.append(bn[i] ?? blankSheet(), ar[i] ?? blankSheet());
        }
    }

    // bilingual: the first Bangla page must be a left (even) page; otherwise start on a right (odd) page
    const sheets = [...document.querySelectorAll('.sheet')];
    const first = document.querySelector('.sheet.page');
    if (first) {
        const pageNumber = sheets.indexOf(first) + 1;
        if ((pageNumber % 2 === 0) !== BILINGUAL) {
            first.before(blankSheet());
        }
    }

    window.pdfReady = true;
})();
</script>
</body>
</html>
