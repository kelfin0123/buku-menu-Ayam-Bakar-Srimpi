<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    /**
     * Create order from cart data
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'table_number' => 'required|string|max:10',
            'payment_method' => 'required|in:cash,qris',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        // Calculate total and prepare items
        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
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
                'table_number' => $validated['table_number'],
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'shipping_cost' => 0, // No shipping for dine-in
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

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat',
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'total' => (int) $order->total,
                    'payment_method' => $order->payment_method,
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
