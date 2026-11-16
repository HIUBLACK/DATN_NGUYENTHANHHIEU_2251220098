<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\RequireRole;
use App\Services\AuthService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RequireRole::class,
        ]);
        $middleware->web(append: [EnsureActiveAccount::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn ($request) => route(app(AuthService::class)->destination($request->user())));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['token', 'current_password', 'password', 'password_confirmation']);
    })->create();
