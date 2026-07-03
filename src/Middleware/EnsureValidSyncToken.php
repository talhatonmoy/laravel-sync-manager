<?php

namespace DeployCar\LaravelSyncManager\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureValidSyncToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = (string) config('sync.receiver.api_key');
        $provided = (string) $request->header('X-Sync-Key');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'Invalid sync token.',
            ], 401);
        }

        // The default key is publicly known; reject it everywhere except
        // local development so staging/preview/CI environments are protected.
        if (! app()->environment('local') && $expected === 'change-me') {
            abort(403, 'The default API key is not allowed outside local development.');
        }

        return $next($request);
    }
}
