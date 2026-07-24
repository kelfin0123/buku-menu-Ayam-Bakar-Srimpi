<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\PublicWebsiteContentController;
use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HeroBannerController extends Controller
{
    public function __construct(private readonly PublicWebsiteContentController $presenter) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => HeroBanner::query()
            ->orderBy('sort_order')->orderByDesc('id')->get()
            ->map(fn (HeroBanner $item) => $this->presenter->heroData($item))]);
    }

    public function show(HeroBanner $heroBanner): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->presenter->heroData($heroBanner)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $path = $this->storeImage($request);
        if ($path) {
            $data['image'] = $path;
            $data['image_url'] = null;
        }
        $banner = HeroBanner::create($data);
        $this->clearCache();

        return response()->json(['success' => true, 'data' => $this->presenter->heroData($banner)], 201);
    }

    public function update(Request $request, HeroBanner $heroBanner): JsonResponse
    {
        $data = $this->validated($request);
        $newPath = $this->storeImage($request);
        $oldPath = $heroBanner->image;
        if ($newPath) {
            $data['image'] = $newPath;
            $data['image_url'] = null;
        }
        $heroBanner->update($data);
        if ($newPath && $oldPath && str_starts_with($oldPath, 'hero-banners/')) {
            Storage::disk('public')->delete($oldPath);
        }
        $this->clearCache();

        return response()->json(['success' => true, 'data' => $this->presenter->heroData($heroBanner->fresh())]);
    }

    public function destroy(HeroBanner $heroBanner): JsonResponse
    {
        $path = $heroBanner->image;
        $heroBanner->delete();
        if ($path && str_starts_with($path, 'hero-banners/')) {
            Storage::disk('public')->delete($path);
        }
        $this->clearCache();

        return response()->json(['success' => true]);
    }

    public function toggle(HeroBanner $heroBanner): JsonResponse
    {
        $heroBanner->update(['is_active' => ! $heroBanner->is_active]);
        $this->clearCache();

        return response()->json(['success' => true, 'data' => $this->presenter->heroData($heroBanner)]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:hero_banners,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);
        foreach ($validated['items'] as $item) {
            HeroBanner::whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        $this->clearCache();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'highlight_text' => ['nullable', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => [
                'nullable',
                'string',
                'max:500',
                'regex:/^(?:#|\/(?!\/)|https?:\/\/)/i',
            ],
            'image_url' => ['nullable', 'url', 'max:1000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        Storage::disk('public')->makeDirectory('hero-banners');

        return $request->file('image')->store('hero-banners', 'public');
    }

    private function clearCache(): void
    {
        Cache::forget('public.hero_banners');
    }
}
