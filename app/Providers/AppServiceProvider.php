<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath && !file_exists($dbPath) && $dbPath !== ':memory:') {
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                touch($dbPath);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        try {
            if (\App\Models\Category::count() === 0) {
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            // Ignore database errors during boot
        }
    }
}
