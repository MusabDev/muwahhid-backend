<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $book->title_bn }}</title>
<style>
{!! $fontCss !!}
* { box-sizing: border-box; }
body { margin: 0; color: #1a1a1a; font-size: 11.5pt; line-height: 1.75; }
.ar { font-family: var(--font-ar); direction: rtl; text-align: justify; font-size: 14pt; line-height: 1.9; }
.bn { font-family: var(--font-bn); direction: ltr; text-align: justify; }
.sheet { break-after: page; display: flex; flex-direction: column; min-height: 100%; }
.sheet:last-child { break-after: auto; }
.cover { height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; gap: 1.2em; border: 3px double #8a6d3b; padding: 2em; }
.cover h1 { margin: 0; font-size: 22pt; line-height: 1.5; }
.cover .author { font-size: 13pt; color: #555; }
.toc h2 { text-align: center; }
.toc ul { list-style: none; padding: 0; margin: 0; }
.toc ul ul { padding-inline-start: 1.2em; }
.toc li .row { display: flex; gap: .5em; }
.toc li .row .title { flex: 1; }
.page-content { flex: 1; }
.page-content p { margin: 0 0 .6em; }
.page-content h3 { text-align: center; margin: .6em 0; font-size: 1.2em; }
.page-content blockquote { margin: .5em 1.5em; padding-inline-start: .8em; border-inline-start: 3px solid #c8b88a; }
.page-content .li { margin: 0 0 .3em; padding-inline-start: 1.2em; position: relative; }
.page-content .li::before { content: '•'; position: absolute; inset-inline-start: .2em; }
.page-content .poetry { text-align: center; margin: 0 0 .2em; }
.fn-ref { font-size: .7em; vertical-align: super; line-height: 0; }
.footnotes { border-top: 1px solid #999; margin-top: 1em; padding-top: .4em; font-size: .8em; line-height: 1.6; }
.footnotes p { margin: 0 0 .25em; }
.footnotes .marker { font-weight: bold; }
.blank { break-after: page; height: 1px; }
.bilingual-start { break-before: left; }
</style>
</head>
<body>

<section class="sheet cover">
    @if ($variant !== \App\Enums\ExportVariant::Bangla)
        <h1 class="ar">{{ $book->title_ar }}</h1>
        <div class="author ar">{{ $book->author->name_ar }}</div>
    @endif
    @if ($variant !== \App\Enums\ExportVariant::Arabic)
        <h1 class="bn">{{ $book->title_bn }}</h1>
        <div class="author bn">{{ $book->author->name_bn }}</div>
    @endif
</section>

@if ($book->tocEntries->isNotEmpty())
    <section class="sheet toc {{ $variant === \App\Enums\ExportVariant::Arabic ? 'ar' : 'bn' }}">
        <h2>{{ $variant === \App\Enums\ExportVariant::Arabic ? 'الفهرس' : 'সূচিপত্র' }}</h2>
        @include('exports.toc', ['entries' => $book->tocEntries, 'variant' => $variant])
    </section>
@endif

@foreach ($pages as $page)
    @foreach ($variant === \App\Enums\ExportVariant::Bilingual ? ['bn', 'ar'] : [$variant->value] as $lang)
        <section class="sheet {{ $lang }} {{ $loop->parent->first && $variant === \App\Enums\ExportVariant::Bilingual && $lang === 'bn' ? 'bilingual-start' : '' }}" id="p-{{ $page['number'] }}-{{ $lang }}">
            <div class="page-content">
                @foreach ($page[$lang] as $block)
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
            @if ($page['footnotes_'.$lang]->isNotEmpty())
                <div class="footnotes">
                    @foreach ($page['footnotes_'.$lang] as $footnote)
                        <p><span class="marker">({{ $footnote->marker }})</span> {!! \App\Support\MarkdownText::toInlineHtml($footnote->text_md) !!}</p>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
@endforeach

</body>
</html>
