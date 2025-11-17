<?php

namespace App\Providers;

use App\Models\Submission;
use App\Models\Setting;
use App\Observers\SubmissionObserver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

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
        RateLimiter::for('filament', function (Request $request) {
            return Limit::perMinute(500)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
