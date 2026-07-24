<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\FirebaseMessagingService;
use App\Services\NewOrderNotificationPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendNewOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public string $notificationId;

    public function __construct(public readonly int $orderId)
    {
        $this->notificationId = (string) Str::uuid();
    }

    public function handle(
        FirebaseMessagingService $messaging,
        NewOrderNotificationPolicy $policy,
    ): void {
        $order = DB::transaction(function () use ($policy): ?Order {
            $order = Order::query()->lockForUpdate()->find($this->orderId);
            if (! $order ||
                $order->new_order_notification_sent_at ||
                ! $policy->isEligible($order)) {
                return null;
            }
            if ($order->new_order_notification_id &&
                $order->new_order_notification_id !== $this->notificationId) {
                return null;
            }
            $order->update(['new_order_notification_id' => $this->notificationId]);

            return $order->fresh();
        });
        if (! $order) {
            return;
        }

        $sent = $messaging->sendNewOrder($order);
        $order->update(['new_order_notification_sent_at' => now()]);
        Log::info('New order FCM completed', [
            'order_id' => $order->id,
            'notification_id' => $this->notificationId,
            'device_count' => $sent,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('New order FCM failed', [
            'order_id' => $this->orderId,
            'notification_id' => $this->notificationId,
            'exception' => get_class($exception),
        ]);
    }
}
