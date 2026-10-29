<ul>
    @foreach ($entries as $entry)
        <li>
            <div class="row">
                <span class="title">
                    @if ($variant === \App\Enums\ExportVariant::Arabic)
                        {{ $entry->title_ar }}
                    @elseif ($variant === \App\Enums\ExportVariant::Bangla)
                        {{ $entry->title_bn }}
                    @else
                        {{ $entry->title_bn }} <span class="ar">— {{ $entry->title_ar }}</span>
                    @endif
                </span>
                <span class="page">{{ $entry->page_number }}</span>
            </div>
            @if ($entry->relationLoaded('children') && $entry->children->isNotEmpty())
                @include('exports.toc', ['entries' => $entry->children, 'variant' => $variant])
            @endif
        </li>
    @endforeach
</ul>
