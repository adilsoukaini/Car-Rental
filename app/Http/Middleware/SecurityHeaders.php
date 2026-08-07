<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Adds baseline security headers to every response passing through the
     * web middleware group. HSTS is deliberately production-only — sending
     * it over plain HTTP in a dev environment would mark the local host as
     * HTTPS-only in the browser and make local testing painful.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // CSP — allow self, Stripe, and fonts. Adjust if adding analytics/CDN later.
        // fonts.bunny.net must appear in BOTH style-src (the <link> stylesheet
        // load) and font-src (the @font-face file loads it references) — the
        // two are governed by different directives; style-src was the one that
        // actually blocked the fonts when verified in a real browser.
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' https://js.stripe.com; ".
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
            "img-src 'self' data: https://picsum.photos https://*.picsum.photos https://lh3.googleusercontent.com; ".
            "font-src 'self' https://fonts.bunny.net; ".
            'frame-src https://js.stripe.com; '.
            "connect-src 'self' https://api.stripe.com;"
        );

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
