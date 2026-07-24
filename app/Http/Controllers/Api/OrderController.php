<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirestoreOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OrderController extends Controller
{
    public function __construct(
        private readonly FirestoreOrderService $firestoreOrders,
    ) {}

    /**
     * Get order by order_code
     */
    public function showByCode(Request $request, string $orderCode)
    {
        $order = Order::query()
            ->with('items')
            ->where('order_code', $orderCode)
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Get incoming orders (new orders waiting for acceptance)
     */
    public function incoming()
    {
        $orders = Order::query()
            ->with('items')
            ->incoming()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn (Order $order) => $this->formatOrder($order))->values(),
        ]);
    }

    public function confirmDeliveryFee(Request $request, Order $order)
    {
        abort_unless($order->is_delivery, 422, 'Pesanan ini bukan pesanan antar.');
        $validated = $request->validate([
            'delivery_fee' => 'required|numeric|min:0|max:99999999.99',
        ]);

        $order->forceFill([
            'delivery_fee' => $validated['delivery_fee'],
            'delivery_fee_status' => 'confirmed',
            'shipping_cost' => $validated['delivery_fee'],
            'total' => (float) $order->subtotal + (float) $validated['delivery_fee'],
        ])->save();
        $order = $order->fresh('items.product');
        $this->firestoreOrders->sync($order);

        return response()->json([
            'success' => true,
            'message' => 'Ongkos kirim berhasil dikonfirmasi.',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Accept an order
     */
    public function accept(Request $request, Order $order)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
        ]);

        $order->forceFill([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => $order->payment_method === Order::PAYMENT_METHOD_CASH
                ? Order::PAYMENT_STATUS_PAID
                : $order->payment_status,
            'employee_id' => Arr::get($validated, 'employee_id') ?? $request->user()?->id,
            'accepted_at' => now(),
            'finished_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();
        $this->firestoreOrders->sync($order->fresh('items.product'));

        return response()->json([
            'success' => true,
            'message' => 'Pesanan diterima.',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Reject an order
     */
    public function reject(Request $request, Order $order)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $order->forceFill([
            'status' => Order::STATUS_CANCELLED,
            'employee_id' => Arr::get($validated, 'employee_id') ?? $request->user()?->id,
            'rejected_at' => now(),
            'finished_at' => null,
            'accepted_at' => $order->accepted_at,
            'rejection_reason' => Arr::get($validated, 'rejection_reason') ?? 'Tidak ada alasan.',
        ])->save();
        $this->firestoreOrders->sync($order->fresh('items.product'));

        return response()->json([
            'success' => true,
            'message' => 'Pesanan ditolak.',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Get employee activities (processing, ready, completed orders)
     */
    public function activities()
    {
        $orders = Order::query()
            ->with('items')
            ->whereIn('status', [
                Order::STATUS_PROCESSING,
                Order::STATUS_READY,
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])
            ->orderByDesc('accepted_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn (Order $order) => $this->formatOrder($order))->values(),
        ]);
    }

    /**
     * Mark order as ready to serve
     */
    public function ready(Order $order)
    {
        $order->forceFill([
            'status' => Order::STATUS_READY,
        ])->save();
        $this->firestoreOrders->sync($order->fresh('items.product'));

        return response()->json([
            'success' => true,
            'message' => 'Pesanan siap disajikan.',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Complete an order
     */
    public function complete(Request $request, Order $order)
    {
        $actor = $request->validate([
            'employee_uid' => 'required|string|max:128',
            'employee_name' => 'nullable|string|max:255',
            'owner_id' => 'nullable|string|max:128',
            'branch_id' => 'nullable|string|max:128',
        ]);

        $order->forceFill([
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => $order->payment_method === Order::PAYMENT_METHOD_CASH
                ? Order::PAYMENT_STATUS_PAID
                : $order->payment_status,
            'finished_at' => $order->finished_at ?? now(),
        ])->save();
        $order = $order->fresh('items.product');

        if (! $this->firestoreOrders->syncCompletion($order, $actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan sudah selesai, tetapi sinkronisasi transaksi gagal. Silakan tekan Selesai lagi; transaksi tidak akan terduplikasi.',
                'data' => $this->formatOrder($order),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan selesai.',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Get order statistics for owner dashboard
     */
    public function statistics()
    {
        $stats = [
            'waiting_payment' => Order::where('status', Order::STATUS_WAITING_PAYMENT)->count(),
            'paid' => Order::where('payment_status', Order::PAYMENT_STATUS_PAID)->count(),
            'new_order' => Order::where('status', Order::STATUS_NEW_ORDER)->count(),
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'ready' => Order::where('status', Order::STATUS_READY)->count(),
            'completed' => Order::where('status', Order::STATUS_COMPLETED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'expired' => Order::where('status', Order::STATUS_EXPIRED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function markSeen(Request $request, Order $order)
    {
        $order->forceFill([
            'is_seen' => true,
            'seen_at' => $order->seen_at ?? now(),
            'seen_by' => $request->attributes->get('firebase_uid'),
        ])->save();
        $this->firestoreOrders->sync($order->fresh('items.product'));

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $order->customer_address,
            'is_delivery' => (bool) $order->is_delivery,
            'isDelivery' => (bool) $order->is_delivery,
            'order_type' => $order->order_type,
            'orderType' => $order->order_type,
            'delivery_address' => $order->delivery_address,
            'deliveryAddress' => $order->delivery_address,
            'delivery_address_detail' => $order->delivery_address_detail,
            'deliveryAddressDetail' => $order->delivery_address_detail,
            'delivery_note' => $order->delivery_note,
            'deliveryNote' => $order->delivery_note,
            'delivery_fee' => $order->delivery_fee === null ? null : (float) $order->delivery_fee,
            'deliveryFee' => $order->delivery_fee === null ? null : (float) $order->delivery_fee,
            'delivery_fee_status' => $order->delivery_fee_status,
            'deliveryFeeStatus' => $order->delivery_fee_status,
            'table_number' => $order->table_number,
            'total' => (int) $order->total,
            'subtotal' => (int) $order->subtotal,
            'shipping_cost' => (int) $order->shipping_cost,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'employee_id' => $order->employee_id,
            'accepted_at' => $order->accepted_at?->toISOString(),
            'finished_at' => $order->finished_at?->toISOString(),
            'rejected_at' => $order->rejected_at?->toISOString(),
            'rejection_reason' => $order->rejection_reason,
            'expires_at' => $order->expires_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
            'is_seen' => (bool) $order->is_seen,
            'seen_at' => $order->seen_at?->toISOString(),
            'seen_by' => $order->seen_by,
            'items' => $order->items->map(function ($item) {
                return [
                    'product_name' => $item->product_name,
                    'qty' => (int) $item->qty,
                    'subtotal' => (int) $item->subtotal,
                ];
            })->values(),
        ];
    }
}
