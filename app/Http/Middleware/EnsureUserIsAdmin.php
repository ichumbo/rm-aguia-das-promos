<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin() || ! $user->is_active) {
            abort(403, 'Acesso restrito a administradores.');
        }

        return $next($request);
    }
}
