<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(UserRole::tryFrom(...), $roles);
        abort_unless($request->user() && in_array($request->user()->role, $allowed, true), 403);

        return $next($request);
    }
}
