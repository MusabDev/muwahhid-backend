{{-- Inside of the front cover: the book's details, a short description, the site's mark and a link back to the book online. --}}
@php
    use App\Enums\Lang;
    use App\Services\BookPdfRenderer;
    $isArabic = $lang === Lang::Arabic;
    $isSharh = $book->is_sharh && $book->explainer;
    $deathYear = fn ($author) => $author->death_year_ah
        ? ($isArabic ? '(ت '.BookPdfRenderer::localizeDigits($author->death_year_ah, $lang).' هـ)' : '(মৃ. '.BookPdfRenderer::localizeDigits($author->death_year_ah, $lang).' হি.)')
        : '';
    $labels = $isArabic
        ? ['author' => 'المؤلف', 'explainer' => 'الشارح', 'matn' => 'صاحب المتن', 'category' => 'التصنيف', 'pages' => 'عدد الصفحات', 'read' => 'اقرأ الكتاب على الموقع']
        : ['author' => 'লেখক', 'explainer' => 'ব্যাখ্যাকার', 'matn' => 'মূল গ্রন্থকার', 'category' => 'বিষয়', 'pages' => 'পৃষ্ঠা', 'read' => 'অনলাইনে পড়ুন'];
@endphp
<section class="sheet colophon {{ $lang->value }}" lang="{{ $lang->value }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
    <div class="colophon-book">
        @include('exports.ornament', ['shape' => 'star', 'class' => 'colophon-star'])
        <p class="colophon-title-ar" lang="ar" dir="rtl">{{ $book->title_ar }}</p>
        @unless ($isArabic)
            <p class="colophon-title-bn">{{ $book->title_bn }}</p>
        @endunless
        @include('exports.ornament', ['shape' => 'divider', 'class' => 'colophon-divider'])

        <dl class="colophon-facts">
            @if ($isSharh)
                <div><dt>{{ $labels['explainer'] }}</dt><dd>{{ $isArabic ? $book->explainer->name_ar : $book->explainer->name_bn }} {{ $deathYear($book->explainer) }}</dd></div>
                <div><dt>{{ $labels['matn'] }}</dt><dd>{{ $isArabic ? $book->author->name_ar : $book->author->name_bn }} {{ $deathYear($book->author) }}</dd></div>
            @elseif ($book->author)
                <div><dt>{{ $labels['author'] }}</dt><dd>{{ $isArabic ? $book->author->name_ar : $book->author->name_bn }} {{ $deathYear($book->author) }}</dd></div>
            @endif
            @if ($book->category)
                <div><dt>{{ $labels['category'] }}</dt><dd>{{ $isArabic ? $book->category->name_ar : $book->category->name_bn }}</dd></div>
            @endif
            <div><dt>{{ $labels['pages'] }}</dt><dd>{{ BookPdfRenderer::localizeDigits($book->pages->count(), $lang) }}</dd></div>
        </dl>

        @if (! $isArabic && $book->about_md)
            <div class="colophon-about">
                @foreach (preg_split('/\n\s*\n/', trim($book->about_md)) as $paragraph)
                    <p>{!! \App\Support\MarkdownText::toInlineHtml($paragraph) !!}</p>
                @endforeach
            </div>
        @endif
    </div>

    <footer class="colophon-brand">
        <div class="brand">
            {{-- StarMark from the web app's brand.tsx --}}
            <svg viewBox="0 0 40 40" class="brand-mark" aria-hidden="true">
                <g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
                    <path d="M20 3 25 15 37 20 25 25 20 37 15 25 3 20 15 15Z" />
                    <path d="M8 8 20 13 32 8 27 20 32 32 20 27 8 32 13 20Z" />
                </g>
                <circle cx="20" cy="20" r="3.2" fill="currentColor" />
            </svg>
            <span class="brand-name">{{ $isArabic ? 'الموحِّد' : 'আল-মুওয়াহহিদ' }}</span>
        </div>
        <a class="colophon-link" href="{{ $bookUrl }}">
            <span>{{ $labels['read'] }}</span>
            <span class="colophon-url" dir="ltr">{{ preg_replace('#^https?://#', '', $bookUrl) }}</span>
        </a>
    </footer>
</section>
