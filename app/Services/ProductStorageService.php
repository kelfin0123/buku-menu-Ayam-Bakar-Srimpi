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
        if (!file_exists($publicStorage)) {
            // create storage link using artisan command
            try {
                \Artisan::call('storage:link');
            } catch (\Throwable $e) {
                // ignore, best-effort
            }
        }
    }

    public function store(UploadedFile $file): string
    {
        $this->ensureStorageLink();

        $ext = $file->getClientOriginalExtension();
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs($this->directory, $filename, $this->disk);

        return $path; // e.g. products/uuid.jpg
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk($this->disk)->delete($path);
    }
}
