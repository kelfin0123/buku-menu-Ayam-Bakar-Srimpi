<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Order;
use App\Observers\OrderObserver;

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

        // Ensure storage directories exist (Railway ephemeral filesystem safety net)
        $this->ensureStorageDirectoriesExist();

        // Ensure storage link exists
        $this->ensureStorageLink();

        // Register model observers to create activity entries for employees
        Order::observe(OrderObserver::class);
    }

    /**
     * Ensure all storage directories required by the application exist.
     */
    private function ensureStorageDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/public'),
            storage_path('app/public/products'),
            storage_path('logs'),
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                try {
                    mkdir($dir, 0755, true);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * Ensure the public/storage symlink exists and points to the correct target.
     */
    private function ensureStorageLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link)) {
            // Symlink exists, verify it's correct
            if (realpath($link) !== realpath($target)) {
                try {
                    unlink($link);
                    symlink($target, $link);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return;
        }

        if (file_exists($link) && ! is_link($link)) {
            // Folder/file exists but is not a symlink, don't overwrite it
            return;
        }

        // Create symlink
        try {
            symlink($target, $link);
        } catch (\Throwable $e) {
            // Silently fail - symlink might not be supported on this environment
            report($e);
        }
    }
}
