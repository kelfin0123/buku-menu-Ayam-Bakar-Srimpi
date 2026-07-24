<?php

namespace App\Services;

use App\Jobs\SendNewOrderNotification;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class NewOrderNotificationDispatcher
{
    public function __construct(
        private readonly NewOrderNotificationPolicy $policy,
    ) {}

    public function dispatchIfEligible(Order $order): bool
    {
        $order->refresh();

        if (! $this->policy->isEligible($order) ||
            $order->new_order_notification_sent_at) {
            return false;
        }

        try {
            SendNewOrderNotification::dispatch($order->id)->afterCommit();

            return true;
        } catch (\Throwable $exception) {
            Log::warning('New order notification could not be queued', [
                'order_id' => $order->id,
                'exception' => get_class($exception),
            ]);

            return false;
        }
    }
}
