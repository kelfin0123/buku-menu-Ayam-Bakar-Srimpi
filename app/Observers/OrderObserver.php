<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Order;

class OrderObserver
{
    public function created(Order $order): void
    {
        Activity::create([
            'order_id' => $order->id,
            'type' => 'order_created',
            'data' => [
                'order_code' => $order->order_code,
                'customer_name' => $order->customer_name,
                'total' => $order->total,
            ],
        ]);
    }
}
