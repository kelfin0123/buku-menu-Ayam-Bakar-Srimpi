<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    private MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Generate QRIS payment for an order
     */
    public function generateQris(Order $order): JsonResponse
    {
        if ($order->payment_method !== Order::PAYMENT_METHOD_QRIS) {
            return response()->json([
                'success' => false,
                'message' => 'Metode pembayaran bukan QRIS',
            ], 400);
        }

        if ($order->isExpired()) {
            $order->markAsExpired();
            return response()->json([
                'success' => false,
                'message' => 'Pesanan telah kadaluarsa',
            ], 400);
        }

        try {
            $snapToken = $this->midtransService->createSnapToken($order);

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $snapToken,
                    'order_code' => $order->order_code,
                    'total' => (int) $order->total,
                    'expires_at' => $order->expires_at?->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pembayaran QRIS',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Order $order): JsonResponse
    {
        if ($order->isExpired()) {
            $order->markAsExpired();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
                'is_expired' => $order->isExpired(),
                'expires_at' => $order->expires_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Handle Midtrans callback notification
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $notification = $request->all();
            $order = $this->midtransService->handleNotification($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil diproses',
                'data' => [
                    'order_code' => $order->order_code,
                    'payment_status' => $order->payment_status,
                    'order_status' => $order->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses notifikasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm cash payment (called by employee)
     */
    public function confirmCashPayment(Request $request, Order $order): JsonResponse
    {
        if ($order->payment_method !== Order::PAYMENT_METHOD_CASH) {
            return response()->json([
                'success' => false,
                'message' => 'Metode pembayaran bukan tunai',
            ], 400);
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran sudah dikonfirmasi',
            ], 400);
        }

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'status' => Order::STATUS_NEW_ORDER,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran tunai berhasil dikonfirmasi',
            'data' => [
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
            ],
        ]);
    }
}
