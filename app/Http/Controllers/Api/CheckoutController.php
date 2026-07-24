<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\FirestoreOrderService;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly FirestoreOrderService $firestoreOrders,
        private readonly WhatsAppLinkService $whatsApp,
    ) {}

    /**
     * Create order from cart data
     */
    public function store(Request $request): JsonResponse
    {
        $isDelivery = $request->boolean('is_delivery');
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'string',
                'max:20',
                'regex:/^(?:\+?62|0|8)[0-9\s-]{8,17}$/',
            ],
            'table_number' => [
                Rule::requiredIf(! $isDelivery),
                'nullable',
                'string',
                'max:10',
            ],
            'is_delivery' => 'nullable|boolean',
            'delivery_address' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'string',
                'max:500',
            ],
            'delivery_address_detail' => 'nullable|string|max:250',
            'delivery_note' => 'nullable|string|max:250',
            'payment_method' => 'required|in:cash,qris,bank_transfer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,firestore_id',
            'items.*.qty' => 'required|integer|min:1',
        ]);
        if ($isDelivery && $validated['payment_method'] === Order::PAYMENT_METHOD_CASH) {
            return response()->json([
                'message' => 'Pesanan antar hanya dapat dibayar melalui QR atau Transfer Bank.',
                'errors' => [
                    'payment_method' => ['Pesanan antar hanya dapat dibayar melalui QR atau Transfer Bank.'],
                ],
            ], 422);
        }

        // Calculate total and prepare items
        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::where('firestore_id', $item['product_id'])->firstOrFail();
            $lineTotal = $product->final_price * $item['qty'];
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $product->final_price,
                'qty' => $item['qty'],
                'subtotal' => $lineTotal,
            ];
        }

        // Create order with database transaction
        \DB::beginTransaction();
        try {
            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => filled($validated['customer_phone'] ?? null)
                    ? $this->whatsApp->normalizePhone($validated['customer_phone'])
                    : '',
                'customer_address' => null,
                'is_delivery' => $isDelivery,
                'order_type' => $isDelivery ? 'delivery' : 'pickup',
                'delivery_address' => $isDelivery ? $validated['delivery_address'] : null,
                'delivery_address_detail' => $isDelivery ? ($validated['delivery_address_detail'] ?? null) : null,
                'delivery_note' => $isDelivery ? ($validated['delivery_note'] ?? null) : null,
                'table_number' => $validated['table_number'] ?? null,
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'shipping_cost' => 0, // No shipping for dine-in
                'delivery_fee' => null,
                'delivery_fee_status' => $isDelivery ? 'pending' : null,
                'total' => $subtotal,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'status' => Order::STATUS_WAITING_PAYMENT,
                'expires_at' => now()->addMinutes(5), // 5 minutes expiration
            ]);

            // Create order items
            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            \DB::commit();

            $this->firestoreOrders->sync($order->fresh('items.product'));

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat',
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'total' => (int) $order->total,
                    'payment_method' => $order->payment_method,
                    'is_delivery' => (bool) $order->is_delivery,
                    'order_type' => $order->order_type,
                    'delivery_fee' => $order->delivery_fee,
                    'delivery_fee_status' => $order->delivery_fee_status,
                    'expires_at' => $order->expires_at?->toISOString(),
                ],
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat pesanan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
