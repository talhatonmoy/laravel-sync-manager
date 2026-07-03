<?php

namespace DeployCar\LaravelSyncManager\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeSyncManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // When require_auth is enabled, the gate check applies in all
        // environments (including local). Otherwise local is a free pass.
        if (config('sync.ui.require_auth', false) || ! app()->environment('local')) {
            if (! Gate::check('viewSyncManager')) {
                abort(403, 'Unauthorized access to Sync Manager UI.');
            }
        }

        return $next($request);
    }
}
