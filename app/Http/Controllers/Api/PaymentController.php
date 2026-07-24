<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirestoreOrderService;
use App\Services\MidtransPaymentService;
use App\Services\NewOrderNotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MidtransPaymentService $midtransPaymentService,
        private readonly FirestoreOrderService $firestoreOrders,
        private readonly NewOrderNotificationDispatcher $notifications,
    ) {}

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
            $snapToken = $this->midtransPaymentService->createSnapToken($order);

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $snapToken,
                    'order_code' => $order->order_code,
                    'total' => (int) $order->total,
                    'expires_at' => $order->expires_at?->toISOString(),
                ],
            ]);
        } catch (\Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak dapat diproses. Midtrans belum dikonfigurasi atau kredensial tidak valid.',
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

    public function chargePos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_type' => ['required', 'in:qris,gopay,ovo'],
            'order_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'phone_number' => ['nullable', 'required_if:payment_type,ovo', 'string', 'max:20'],
        ]);

        try {
            $data = $this->midtransPaymentService->chargePos(
                $validated['payment_type'],
                $validated['order_id'],
                $validated['amount'],
                $validated['phone_number'] ?? null,
            );

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak dapat diproses. Periksa konfigurasi Midtrans.',
            ], 502);
        }
    }

    public function posStatus(string $orderId): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $orderId) === 1, 422);

        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_status' => $this->midtransPaymentService
                        ->posTransactionStatus($orderId),
                ],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Status pembayaran tidak dapat diperiksa.',
            ], 502);
        }
    }

    /**
     * Handle Midtrans callback notification
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $notification = $request->all();
            $order = $this->midtransPaymentService->handleNotification($notification);
            $this->firestoreOrders->sync($order->fresh('items.product'));
            $this->notifications->dispatchIfEligible($order);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil diproses',
                'data' => [
                    'order_code' => $order->order_code,
                    'payment_status' => $order->payment_status,
                    'order_status' => $order->status,
                ],
            ]);
        } catch (\Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses notifikasi',
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
        $this->firestoreOrders->sync($order->fresh('items.product'));
        $this->notifications->dispatchIfEligible($order);

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
