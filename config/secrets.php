<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secret Lifecycle Limits
    |--------------------------------------------------------------------------
    |
    | Server-side bounds for client supplied lifecycle options. The client
    | only sends a TTL in seconds; the absolute expiry is computed here.
    |
    */

    'min_ttl_seconds' => (int) env('SECRET_MIN_TTL_SECONDS', 60),

    'max_ttl_seconds' => (int) env('SECRET_MAX_TTL_SECONDS', 604800),

    'max_views' => (int) env('SECRET_MAX_VIEWS', 10),

    /*
    |--------------------------------------------------------------------------
    | Payload Limits
    |--------------------------------------------------------------------------
    |
    | The server cannot see the plaintext, only the encrypted envelope. The
    | request body limit is checked before the JSON body is parsed.
    |
    */

    'max_envelope_bytes' => (int) env('SECRET_MAX_ENVELOPE_BYTES', 131072),

    'max_request_bytes' => (int) env('SECRET_MAX_REQUEST_BYTES', 135168),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'create_per_minute' => (int) env('SECRET_CREATE_PER_MINUTE', 5),
        'create_per_hour' => (int) env('SECRET_CREATE_PER_HOUR', 20),
        'claim_per_minute' => (int) env('SECRET_CLAIM_PER_MINUTE', 30),
        'delete_per_minute' => (int) env('SECRET_DELETE_PER_MINUTE', 10),
        'api_per_minute' => (int) env('SECRET_API_PER_MINUTE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma separated proxy IPs/CIDRs. Required in production so that rate
    | limits use the real client IP and HTTPS detection works.
    |
    */

    'trusted_proxies' => array_values(array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')))),

];
