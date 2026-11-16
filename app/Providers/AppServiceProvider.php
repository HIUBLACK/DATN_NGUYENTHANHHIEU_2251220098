<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Stable aliases match the CHECK constraint on activity_logs.subject_type.
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'job' => Job::class,
        ]);
    }
}
