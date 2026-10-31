{{-- The eight-pointed star and diamond rule used on the web app's covers. --}}
@if ($shape === 'star')
    <svg viewBox="0 0 20 20" class="{{ $class }}" aria-hidden="true">
        <g fill="currentColor">
            <rect x="4" y="4" width="12" height="12" />
            <rect x="4" y="4" width="12" height="12" transform="rotate(45 10 10)" />
        </g>
        <circle cx="10" cy="10" r="2.2" fill="black" fill-opacity="0.35" />
    </svg>
@else
    <svg viewBox="0 0 80 8" class="{{ $class }}" aria-hidden="true">
        <line x1="0" y1="4" x2="33" y2="4" stroke="currentColor" stroke-width="0.8" />
        <line x1="47" y1="4" x2="80" y2="4" stroke="currentColor" stroke-width="0.8" />
        <path d="M40 0.5 43.5 4 40 7.5 36.5 4Z" fill="currentColor" />
        <circle cx="35" cy="4" r="0.9" fill="currentColor" />
        <circle cx="45" cy="4" r="0.9" fill="currentColor" />
    </svg>
@endif
