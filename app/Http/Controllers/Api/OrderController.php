<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OrderController extends Controller
{
    public function pending()
    {
        $orders = Order::query()
            ->with('items')
            ->whereIn('status', ['menunggu_konfirmasi', 'waiting'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(function (Order $order) {
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

    public function accept(Request $request, Order $order)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
        ]);

        $order->forceFill([
            'status' => 'diproses',
            'employee_id' => Arr::get($validated, 'employee_id') ?? $request->user()?->id,
            'accepted_at' => now(),
            'finished_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan diterima.',
            'data' => $this->formatOrder($order),
        ]);
    }

    public function reject(Request $request, Order $order)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $order->forceFill([
            'status' => 'ditolak',
            'employee_id' => Arr::get($validated, 'employee_id') ?? $request->user()?->id,
            'rejected_at' => now(),
            'finished_at' => null,
            'accepted_at' => $order->accepted_at,
            'rejection_reason' => Arr::get($validated, 'rejection_reason') ?? 'Tidak ada alasan.',
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan ditolak.',
            'data' => $this->formatOrder($order),
        ]);
    }

    public function employeeActivities()
    {
        $orders = Order::query()
            ->with('items')
            ->whereIn('status', ['diproses', 'selesai', 'ditolak'])
            ->orderByDesc('accepted_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn (Order $order) => $this->formatOrder($order))->values(),
        ]);
    }

    public function finish(Order $order)
    {
        $order->forceFill([
            'status' => 'selesai',
            'finished_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan selesai.',
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
            'table_number' => $order->table_number,
            'total' => (int) $order->total,
            'subtotal' => (int) $order->subtotal,
            'shipping_cost' => (int) $order->shipping_cost,
            'status' => $order->status,
            'employee_id' => $order->employee_id,
            'accepted_at' => $order->accepted_at?->toISOString(),
            'finished_at' => $order->finished_at?->toISOString(),
            'rejected_at' => $order->rejected_at?->toISOString(),
            'rejection_reason' => $order->rejection_reason,
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
