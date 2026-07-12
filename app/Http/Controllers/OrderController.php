<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Tampilkan detail / status satu pesanan berdasarkan order_code.
     */
    public function show(string $orderCode): View
    {
        $order = Order::query()
            ->with('items')
            ->where('order_code', $orderCode)
            ->firstOrFail();

        return view('customer.order', [
            'order' => $order,
        ]);
    }
}
