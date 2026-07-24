<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductStorageService
{
    protected string $disk = 'public';

    protected string $directory = 'products';

    public function ensureStorageLink(): void
    {
        $publicStorage = public_path('storage');
        if (! file_exists($publicStorage)) {
            try {
                \Artisan::call('storage:link');
            } catch (\Throwable $e) {
                Log::error('ProductStorageService: failed to create storage:link', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Ensure the disk's target directory exists and is writable before upload.
     */
    private function ensureDirectoriesExist(): void
    {
        $path = storage_path('app/public/'.$this->directory);

        if (! is_dir($path)) {
            try {
                mkdir($path, 0755, true);
            } catch (\Throwable $e) {
                Log::error('ProductStorageService: failed to create storage directory', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! is_dir($path) || ! is_writable($path)) {
            Log::error('ProductStorageService: storage directory is missing or not writable', [
                'path' => $path,
            ]);

            throw new \RuntimeException('Direktori penyimpanan produk tidak tersedia atau tidak dapat ditulis.');
        }
    }

    public function store(UploadedFile $file): array
    {
        $this->ensureStorageLink();
        $this->ensureDirectoriesExist();

        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $ext = $extensionByMime[$file->getMimeType()] ?? null;
        if ($ext === null) {
            throw new \InvalidArgumentException('MIME type gambar tidak didukung.');
        }

        $filename = Str::uuid()->toString().'_'.now()->timestamp.'.'.$ext;
        Storage::disk($this->disk)->makeDirectory($this->directory);

        try {
            $path = $file->storeAs($this->directory, $filename, $this->disk);
        } catch (\Throwable $e) {
            Log::error('ProductStorageService: failed to store uploaded file', [
                'filename' => $filename,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($path === false) {
            Log::error('ProductStorageService: storeAs returned false', [
                'filename' => $filename,
            ]);

            throw new \RuntimeException('Gagal menyimpan gambar produk.');
        }

        return [
            'path' => $path,
            'url' => url(Storage::disk($this->disk)->url($path)),
        ];
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $relativePath = $this->toStoragePath($path);
        if (! $relativePath) {
            return;
        }

        Storage::disk($this->disk)->delete($relativePath);
    }

    public function toStoragePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path);
            $path = $parsed['path'] ?? '';
        }

        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path, $count);

        return $count > 0 ? $path : $path;
    }
}
