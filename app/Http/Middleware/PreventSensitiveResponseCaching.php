<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventSensitiveResponseCaching
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isSensitiveRequest = $request->is('app', 'app/*')
            || $request->routeIs(
                'filament.app.*',
                '*livewire.update',
                'livewire.upload-file',
                'livewire.preview-file',
            );

        if ($isSensitiveRequest) {
            $response->headers->set(
                'Cache-Control',
                'private, no-store',
            );

            $response->headers->remove('ETag');
            $response->headers->remove('Last-Modified');
            $response->headers->remove('Expires');
        }

        return $response;
    }
}
