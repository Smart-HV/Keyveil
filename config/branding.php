<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Runtime branding without a rebuild. The app name comes from APP_NAME.
    | Logo and favicon must be same-origin files inside public/ (the CSP
    | blocks external images), e.g. BRAND_LOGO=/branding/logo.svg.
    | Colors and fonts live in resources/css/theme.css (see docs/theming.md).
    |
    */

    'logo' => env('BRAND_LOGO'),

    'favicon' => env('BRAND_FAVICON', '/favicon.svg'),

    // Hide the text name next to the logo when the logo already contains it.
    'show_name' => (bool) env('BRAND_SHOW_NAME', true),

];
