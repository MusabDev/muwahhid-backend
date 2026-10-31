{{-- Port of the web app's BookCover (book-cover.tsx), drawn full-bleed on the first PDF page. --}}
@php
    $isSharh = $book->is_sharh && $book->explainer;
    $isArabic = $variant === \App\Enums\ExportVariant::Arabic;
@endphp
@if ($cover['image'])
    <section class="sheet cover">
        <img class="cover-image" src="{{ $cover['image'] }}" alt="{{ $isArabic ? $book->title_ar : $book->title_bn }}">
        <div class="cover-spine-shade"></div>
    </section>
@else
    <section class="sheet cover" style="background: {{ $cover['background'] }}">
        {{-- quarter binding: a darker leather spine with a gold rule along its edge --}}
        <div class="cover-spine"></div>
        <div class="cover-spine-rule"></div>
        <div class="cover-spine-fade"></div>

        {{-- single fine gold frame with a hairline inside it --}}
        <div class="cover-frame"><div></div></div>

        {{-- gold rosette lattice inside the frame, faded out behind the titles --}}
        <div class="cover-lattice">
            <div style="background-image: url('{{ $cover['pattern'] }}')"></div>
            <div class="cover-lattice-fade" style="background: radial-gradient(ellipse 68% 58% at 50% 55%, {{ $cover['cloth'] }} 42%, transparent 100%)"></div>
        </div>

        <div class="cover-title-ar">
            @include('exports.ornament', ['shape' => 'star', 'class' => 'cover-star'])
            <p lang="ar" dir="rtl" style="font-size: {{ $cover['arabic_size'] }}">{{ $book->title_ar }}</p>
        </div>

        @include('exports.ornament', ['shape' => 'divider', 'class' => 'cover-divider'])

        {{-- the Arabic-only edition carries no Bangla anywhere --}}
        @unless ($isArabic)
            <div class="cover-title-bn">
                <p style="font-size: {{ $cover['bangla_size'] }}">{{ $book->title_bn }}</p>
            </div>
        @endunless

        @if ($book->author)
            <div class="cover-author {{ $isSharh ? 'is-sharh' : '' }} {{ $isArabic ? 'ar' : '' }}" @if ($isArabic) lang="ar" dir="rtl" @endif>
                <span class="cover-author-rule"></span>
                <p class="cover-author-name">{{ $isArabic ? $book->author->name_ar : $book->author->name_bn }}</p>
                @if ($isSharh)
                    <p class="cover-explainer"><span>{{ $isArabic ? 'شرح: ' : 'ব্যাখ্যা: ' }}</span>{{ $isArabic ? $book->explainer->name_ar : $book->explainer->name_bn }}</p>
                @endif
            </div>
        @endif
    </section>
@endif
