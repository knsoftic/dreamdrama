<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSubAdmin()) {
            abort(403, 'Only a sub-admin can view this page.');
        }

        return $next($request);
    }
}
