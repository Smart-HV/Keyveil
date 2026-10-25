<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBody
{
    /**
     * Reject oversized bodies with 413 before the JSON body is parsed.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('secrets.max_request_bytes');
        $declaredLength = $request->headers->get('Content-Length');

        if ($declaredLength !== null && (int) $declaredLength > $limit) {
            return $this->tooLarge();
        }

        if (strlen($request->getContent()) > $limit) {
            return $this->tooLarge();
        }

        return $next($request);
    }

    /**
     * Uniform payload-too-large response.
     */
    protected function tooLarge(): Response
    {
        return response()->json([
            'error' => [
                'code' => 'payload_too_large',
                'message' => 'The request body is too large.',
            ],
        ], 413);
    }
}
