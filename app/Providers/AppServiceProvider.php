<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Settings are loaded ONCE per request, not once for every view,
        // partial and Blade component.
        $this->app->scoped('app.settings', function () {
            try {
                return Schema::hasTable('settings') ? Setting::current() : null;
            } catch (\Throwable $e) {
                // Settings table not migrated yet — views fall back to defaults.
                return null;
            }
        });
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            $settings = $this->app->make('app.settings');

            if ($settings !== null) {
                $view->with('settings', $settings);
            }
        });
    }
}
