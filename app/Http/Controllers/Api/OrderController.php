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
            'data' => $orders->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'customer_name' => $order->customer_name,
                    'table_number' => $order->table_number,
                    'total' => (int) $order->total,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'status' => $order->status,
                    'created_at' => $order->created_at?->toISOString(),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name,
                            'qty' => (int) $item->qty,
                            'subtotal' => (int) $item->subtotal,
                        ];
                    })->values(),
                ];
            })->values(),
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
    public function complete(Order $order)
    {
        $order->forceFill([
            'status' => Order::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
        $this->firestoreOrders->sync($order->fresh('items.product'));

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

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $order->customer_address,
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
