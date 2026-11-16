<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            if (Auth::viaRemember()) {
                app(AuthService::class)->logout($request);

                return $request->expectsJson()
                    ? response()->json(['message' => 'Vui lòng đăng nhập lại.'], 401)
                    : redirect()->route('login');
            }
            $user = User::find($request->user()->id);
            if (! $user || $user->status !== UserStatus::Active) {
                DB::table('sessions')->where('user_id', $request->user()->id)->delete();
                app(AuthService::class)->logout($request);

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Phiên đăng nhập không còn hợp lệ.'], 403);
                }

                return redirect()->route('login')->withErrors(['email' => 'Phiên đăng nhập không còn hợp lệ.']);
            }
            Auth::setUser($user);
            if ($request->session()->get('preview_auth_user') !== $user->id) {
                $request->session()->forget(['candidate_preview', 'company_profile_preview', 'company_profile_owner']);
                $request->session()->put('preview_auth_user', $user->id);
            }
        }

        $response = $next($request);
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
