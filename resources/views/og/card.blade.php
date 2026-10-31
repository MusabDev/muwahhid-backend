{{-- 1200×630 share card (og:image), styled like the web app's cloth book covers. Rendered to PNG by Gotenberg. --}}
@php
    $arSize = match (true) {
        mb_strlen($titleAr ?? '') <= 14 => 84,
        mb_strlen($titleAr ?? '') <= 26 => 68,
        mb_strlen($titleAr ?? '') <= 40 => 54,
        default => 44,
    };
    $bnSize = match (true) {
        mb_strlen($titleBn) <= 18 => 56,
        mb_strlen($titleBn) <= 32 => 46,
        mb_strlen($titleBn) <= 50 => 38,
        default => 32,
    };
@endphp
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<style>
{!! $fontCss !!}
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; }
body { position: relative; background: {{ $cloth['background'] }}; color: #f3e9d2; font-family: var(--font-bn); -webkit-font-smoothing: antialiased; }
.spine { position: absolute; inset: 0 auto 0 0; width: 56px; background: linear-gradient(90deg, rgba(0,0,0,.45), rgba(0,0,0,.28) 60%, rgba(0,0,0,.18)); }
.spine-rule { position: absolute; top: 0; bottom: 0; left: 56px; width: 2px; background: #cdad66; opacity: .7; }
.lattice { position: absolute; inset: 40px 40px 40px 96px; background-image: url('{{ $pattern }}'); background-size: 150px auto; background-position: center; opacity: .17; }
.lattice-fade { position: absolute; inset: 40px 40px 40px 96px; background: radial-gradient(ellipse 62% 64% at {{ $image ? '60%' : '50%' }} 50%, {{ $cloth['cloth'] }} 45%, transparent 100%); }
.frame { position: absolute; inset: 28px 28px 28px 84px; border: 1.5px solid rgba(205,173,102,.7); }
.frame::after { content: ''; position: absolute; inset: 10px; border: 1px solid rgba(205,173,102,.3); }
.layout { position: absolute; inset: 60px 72px 60px 128px; display: flex; align-items: center; gap: 56px; }
.cover { flex: none; width: 300px; height: 450px; border-radius: 2px 6px 6px 2px; overflow: hidden; box-shadow: 0 24px 50px -12px rgba(0,0,0,.65); }
.cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.text { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: {{ $image ? 'flex-start' : 'center' }}; text-align: {{ $image ? 'left' : 'center' }}; }
.kicker { font-size: 26px; font-weight: 700; letter-spacing: .04em; color: #cdad66; }
.title-ar { margin-top: 14px; font-family: var(--font-ar); font-weight: 700; font-size: {{ $arSize }}px; line-height: 1.35; color: #e4c987; max-width: 100%; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.divider { width: 220px; height: 22px; margin: 10px 0 6px; color: #cdad66; }
.title-bn { font-weight: 700; font-size: {{ $bnSize }}px; line-height: 1.3; color: #f6eedb; max-width: 100%; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.subtitle { margin-top: 18px; font-size: 30px; color: rgba(243,233,210,.82); max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.brand { position: absolute; right: 72px; bottom: 50px; display: flex; align-items: center; gap: 12px; font-size: 24px; color: rgba(243,233,210,.75); }
.brand svg { width: 30px; height: 30px; color: #cdad66; }
.brand b { color: #e4c987; font-weight: 700; }
</style>
</head>
<body>
    <div class="spine"></div>
    <div class="spine-rule"></div>
    <div class="lattice"></div>
    <div class="lattice-fade"></div>
    <div class="frame"></div>

    <div class="layout">
        @if ($image)
            <div class="cover"><img src="{{ $image }}" alt=""></div>
        @endif
        <div class="text">
            @if ($kicker)
                <p class="kicker">{{ $kicker }}</p>
            @endif
            @if ($titleAr)
                <p class="title-ar" lang="ar" dir="rtl">{{ $titleAr }}</p>
                @include('exports.ornament', ['shape' => 'divider', 'class' => 'divider'])
            @endif
            <h1 class="title-bn">{{ $titleBn }}</h1>
            @if ($subtitle)
                <p class="subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    <div class="brand">
        <svg viewBox="0 0 40 40" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M20 3 25 15 37 20 25 25 20 37 15 25 3 20 15 15Z"/><path d="M8 8 20 13 32 8 27 20 32 32 20 27 8 32 13 20Z"/></g></svg>
        <span><b>আল-মুওয়াহহিদ</b> · muwahhid.net</span>
    </div>

    <script>
        document.fonts.ready.then(() => { window.ogReady = true })
    </script>
</body>
</html>
