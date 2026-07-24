<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StorageBootstrapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:bootstrap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure storage directories exist, permissions are correct, and the public/storage symlink is valid';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Bootstrapping storage...');

        $ok = true;
        $ok = $this->ensureDirectories() && $ok;
        $ok = $this->ensureSymlink() && $ok;

        if ($ok) {
            $this->info('Storage bootstrap completed successfully.');

            return self::SUCCESS;
        }

        $this->warn('Storage bootstrap completed with warnings. Check logs for details.');

        return self::FAILURE;
    }

    /**
     * Create required storage directories and set permissions.
     */
    private function ensureDirectories(): bool
    {
        $directories = [
            storage_path('app/public'),
            storage_path('app/public/products'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        $success = true;

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                try {
                    mkdir($dir, 0755, true);
                    $this->line("Created directory: {$dir}");
                } catch (\Throwable $e) {
                    $this->error("Failed to create directory: {$dir} - {$e->getMessage()}");
                    Log::error('StorageBootstrapCommand: failed to create directory', [
                        'path' => $dir,
                        'message' => $e->getMessage(),
                    ]);
                    $success = false;

                    continue;
                }
            }

            try {
                chmod($dir, 0755);
            } catch (\Throwable $e) {
                $this->warn("Failed to set permissions on: {$dir} - {$e->getMessage()}");
                Log::warning('StorageBootstrapCommand: failed to chmod directory', [
                    'path' => $dir,
                    'message' => $e->getMessage(),
                ]);
            }

            if (! is_writable($dir)) {
                $this->error("Directory is not writable: {$dir}");
                Log::error('StorageBootstrapCommand: directory is not writable', ['path' => $dir]);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Ensure the public/storage symlink exists and points to the correct target.
     */
    private function ensureSymlink(): bool
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link)) {
            if (realpath($link) === realpath($target)) {
                $this->info('Storage symlink is valid.');

                return true;
            }

            $this->warn('Storage symlink is broken or points to the wrong target. Recreating...');

            try {
                unlink($link);
            } catch (\Throwable $e) {
                $this->error("Failed to remove broken symlink: {$e->getMessage()}");
                Log::error('StorageBootstrapCommand: failed to unlink broken symlink', [
                    'link' => $link,
                    'message' => $e->getMessage(),
                ]);

                return false;
            }
        } elseif (file_exists($link)) {
            $this->error("A non-symlink file/directory already exists at {$link}. Skipping symlink creation.");
            Log::error('StorageBootstrapCommand: non-symlink already exists at storage link path', [
                'link' => $link,
            ]);

            return false;
        }

        try {
            symlink($target, $link);
            $this->info("Created storage symlink: {$link} -> {$target}");
        } catch (\Throwable $e) {
            $this->error("Failed to create storage symlink: {$e->getMessage()}");
            Log::error('StorageBootstrapCommand: failed to create storage symlink', [
                'link' => $link,
                'target' => $target,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
