<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '';

            return [
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login:'.hash('sha256', $email.'|'.$request->ip())),
            ];
        });
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-setup-start', fn (Request $request) => Limit::perMinute(5)->by('setup-start:'.$request->user()->id));
        RateLimiter::for('password-setup-complete', fn (Request $request) => Limit::perMinute(5)->by('setup-complete:'.$request->user()->id));
        RateLimiter::for('password-email', function (Request $request) {
            $email = is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '';

            return [
                Limit::perMinute(10)->by('password-ip:'.$request->ip()),
                Limit::perHour(5)->by('password-email:'.hash('sha256', $email)),
            ];
        });
        // Stable aliases match the CHECK constraint on activity_logs.subject_type.
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'job' => Job::class,
        ]);
    }
}
