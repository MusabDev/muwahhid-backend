{{-- Flat rows (depth as a class) so the page script can spread them over as many sheets as needed. --}}
@foreach ($entries as $entry)
    @php($lang = $variant === \App\Enums\ExportVariant::Arabic ? \App\Enums\Lang::Arabic : \App\Enums\Lang::Bangla)
    <a class="toc-row depth-{{ $depth }}" href="#p-{{ $entry->page_number }}-{{ $lang->value }}">
        <span class="toc-line">
            <span class="toc-title">{{ $lang === \App\Enums\Lang::Arabic ? $entry->title_ar : $entry->title_bn }}</span>
            <span class="toc-leader"></span>
            <span class="toc-page">{{ \App\Services\BookPdfRenderer::localizeDigits($entry->page_number, $lang) }}</span>
        </span>
        @if ($variant === \App\Enums\ExportVariant::Bilingual && $entry->title_ar)
            <span class="toc-title-ar" lang="ar" dir="rtl">{{ $entry->title_ar }}</span>
        @endif
    </a>
    @if ($entry->relationLoaded('children') && $entry->children->isNotEmpty())
        @include('exports.toc', ['entries' => $entry->children, 'variant' => $variant, 'depth' => $depth + 1])
    @endif
@endforeach
