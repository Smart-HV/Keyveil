<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Apply the security headers required for every page and API response.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->add([
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ]);

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Build the CSP. The Vite dev server needs extra sources and inline styles
     * (HMR injects <style> tags); production allows same-origin assets only.
     * 'wasm-unsafe-eval' is required to compile the bundled Argon2id WASM.
     */
    protected function contentSecurityPolicy(): string
    {
        $vite = '';
        $inlineStyles = '';

        if (Vite::isRunningHot()) {
            $origin = rtrim((string) file_get_contents(Vite::hotFile()));
            $vite = ' '.$origin.' '.preg_replace('/^http/', 'ws', $origin);
            $inlineStyles = " 'unsafe-inline'";
        }

        return implode('; ', [
            "default-src 'none'",
            "script-src 'self' 'wasm-unsafe-eval'".$vite,
            "style-src 'self'".$inlineStyles.$vite,
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'".$vite,
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "manifest-src 'self'",
            "worker-src 'self'",
        ]);
    }
}
