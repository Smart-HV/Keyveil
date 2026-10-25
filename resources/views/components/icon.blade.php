@props(['name'])

<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('lock')
            <rect x="5" y="11" width="14" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
            @break
        @case('copy')
            <rect x="9" y="9" width="11" height="11" rx="2" />
            <path d="M5 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V5" />
            @break
        @case('check')
            <path d="M5 12.5 9.5 17 19 7.5" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('eye')
            <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" />
            <circle cx="12" cy="12" r="3" />
            @break
        @case('alert')
            <path d="M10.3 4.2 2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z" />
            <path d="M12 9.5v4" />
            <path d="M12 17h.01" />
            @break
        @case('trash')
            <path d="M4 7h16" />
            <path d="M9.5 7V4.5h5V7" />
            <path d="m6 7 1 13h10l1-13" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('minus')
            <path d="M5 12h14" />
            @break
        @case('shield')
            <path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6L12 3Z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('views')
            <path d="M4 6h16M4 12h16M4 18h10" />
            @break
    @endswitch
</svg>
