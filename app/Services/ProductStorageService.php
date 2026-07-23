<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
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
                // ignore, best-effort
            }
        }
    }

    public function store(UploadedFile $file): array
    {
        $this->ensureStorageLink();

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
        $path = $file->storeAs($this->directory, $filename, $this->disk);

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
