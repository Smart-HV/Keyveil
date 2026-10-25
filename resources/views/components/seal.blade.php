@props(['state' => 'sealed'])

{{-- Seal: a notary-style stamp with a padlock. States: open, sealed, sealing, unsealed (see app.css). --}}
<svg {{ $attributes->merge(['class' => 'seal is-'.$state]) }} viewBox="0 0 96 96" aria-hidden="true" focusable="false">
    <circle class="seal-notches" cx="48" cy="48" r="45" />
    <circle class="seal-ring" cx="48" cy="48" r="38" />
    <g class="seal-lock">
        <path class="seal-shackle" d="M39 45v-7a9 9 0 0 1 18 0v7" />
        <rect class="seal-body" x="33" y="45" width="30" height="22" rx="4" />
        <path class="seal-keyhole" d="M48 53v6" />
    </g>
</svg>
