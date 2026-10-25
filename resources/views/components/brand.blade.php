<a href="{{ route('home') }}" class="brand" aria-label="{{ config('app.name') }} – Startseite">
    <svg class="brand-mark" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false">
        <path class="brand-veil" d="M16 2.5 28.5 9v14L16 29.5 3.5 23V9L16 2.5Z" />
        <circle class="brand-key" cx="16" cy="14" r="3.5" />
        <path class="brand-key" d="M16 17.5v6" />
    </svg>
    <span>{{ config('app.name') }}</span>
</a>
