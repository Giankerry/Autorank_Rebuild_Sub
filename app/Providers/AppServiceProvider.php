<?php

namespace App\Providers;

use App\Models\Submission;
use App\Models\Setting;
use App\Observers\SubmissionObserver;
use App\Services\ApplicationScoringService;
use App\Services\GoogleDriveService;
use App\Services\ScoringService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('app.settings', function () {
            return Cache::rememberForever('settings', function () {
                try {
                    return Setting::all()->pluck('value', 'key');
                } catch (QueryException $e) {
                    return collect();
                }
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Submission::observe(SubmissionObserver::class);
    }
}
