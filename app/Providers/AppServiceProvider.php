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
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            try {
                if (Schema::hasTable('settings')) {
                    $view->with('settings', Setting::current());
                }
            } catch (\Throwable $e) {
                // Settings table not migrated yet — views fall back to defaults.
            }
        });
    }
}
