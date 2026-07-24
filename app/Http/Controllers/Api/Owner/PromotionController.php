<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\PublicWebsiteContentController;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    public function __construct(private readonly PublicWebsiteContentController $presenter) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Promotion::with('product')
            ->orderBy('sort_order')->orderByDesc('id')->get()
            ->map(fn (Promotion $item) => $this->presenter->promotionData($item))]);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->presenter->promotionData($promotion->load('product'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if ($path = $this->storeImage($request)) {
            $data['image'] = $path;
        }
        $promotion = Promotion::create($data);
        $this->clearCache();

        return response()->json(['success' => true, 'data' => $this->presenter->promotionData($promotion->load('product'))], 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $this->validated($request);
        $oldPath = $promotion->image;
        if ($path = $this->storeImage($request)) {
            $data['image'] = $path;
        }
        $promotion->update($data);
        if (isset($path) && $oldPath && str_starts_with($oldPath, 'promotions/')) {
            Storage::disk('public')->delete($oldPath);
        }
        $this->clearCache();

        return response()->json(['success' => true, 'data' => $this->presenter->promotionData($promotion->fresh('product'))]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $path = $promotion->image;
        $promotion->delete();
        if ($path && str_starts_with($path, 'promotions/')) {
            Storage::disk('public')->delete($path);
        }
        $this->clearCache();

        return response()->json(['success' => true]);
    }

    public function toggle(Promotion $promotion): JsonResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);
        $this->clearCache();

        return response()->json(['success' => true, 'data' => $this->presenter->promotionData($promotion->load('product'))]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:promotions,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);
        foreach ($validated['items'] as $item) {
            Promotion::whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        $this->clearCache();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'discount_type' => ['nullable', 'in:percentage,fixed,special_price'],
            'discount_value' => [
                'nullable',
                'required_if:discount_type,percentage,fixed',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('discount_type') === 'percentage' && (float) $value > 100) {
                        $fail('Diskon persentase tidak boleh lebih dari 100%.');
                    }
                },
            ],
            'promo_price' => [
                'nullable',
                'required_if:discount_type,special_price',
                'numeric',
                'min:0',
            ],
            'badge_text' => ['nullable', 'string', 'max:50'],
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
        Storage::disk('public')->makeDirectory('promotions');

        return $request->file('image')->store('promotions', 'public');
    }

    private function clearCache(): void
    {
        Cache::forget('public.promotions');
    }
}
