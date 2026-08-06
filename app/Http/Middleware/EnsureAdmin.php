<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Autentikasi diperlukan.');
        }

        if (!$user->isAdmin()) {
            abort(403, 'Akses admin diperlukan.');
        }

        return $next($request);
    }
}
