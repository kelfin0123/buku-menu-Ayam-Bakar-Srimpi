<?php

namespace App\Services;

use App\Models\Order;

class NewOrderNotificationPolicy
{
    public function isEligible(Order $order): bool
    {
        if ($order->status !== Order::STATUS_NEW_ORDER) {
            return false;
        }

        if ($order->payment_method === Order::PAYMENT_METHOD_CASH) {
            return true;
        }

        return $order->payment_method === Order::PAYMENT_METHOD_QRIS
            && $order->payment_status === Order::PAYMENT_STATUS_PAID;
    }
}
