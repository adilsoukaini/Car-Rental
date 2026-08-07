<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    /**
     * Tag every request with a correlation ID for distributed debugging.
     *
     * If the client supplied one (X-Correlation-Id request header), it is
     * echoed back unchanged — letting a front-end/proxy/CLI trace its own
     * request through the system. Otherwise a fresh UUID is generated. The
     * ID is returned on the response's X-Correlation-Id header so support
     * can grep logs (request logs, exception traces, queue jobs) by a single
     * value that follows one user action end to end.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Correlation-Id') ?? (string) Str::uuid();

        $response = $next($request);

        $response->headers->set('X-Correlation-Id', $id);

        return $response;
    }
}
