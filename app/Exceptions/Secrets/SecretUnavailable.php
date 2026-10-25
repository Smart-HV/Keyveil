<?php

namespace App\Exceptions\Secrets;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Single, uniform error for unknown, expired, exhausted or deleted secrets
 * and for wrong delete tokens. Callers must not distinguish these states.
 */
class SecretUnavailable extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return self::response();
    }

    /**
     * The uniform "secret unavailable" response body.
     */
    public static function response(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'secret_unavailable',
                'message' => 'This secret is not available.',
            ],
        ], 404);
    }
}
