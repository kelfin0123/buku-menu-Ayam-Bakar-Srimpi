<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WhatsAppLinkService;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderController extends Controller
{
    /**
     * Tampilkan detail / status satu pesanan berdasarkan order_code.
     */
    public function show(string $orderCode, WhatsAppLinkService $whatsApp): View
    {
        $order = Order::query()
            ->with('items')
            ->where('order_code', $orderCode)
            ->firstOrFail();

        $receiptUrl = URL::temporarySignedRoute(
            'receipt.show',
            now()->addDays(7),
            ['orderCode' => $order->order_code],
        );
        try {
            $whatsappUrl = $whatsApp->buildWhatsAppUrl($order, $receiptUrl);
        } catch (InvalidArgumentException) {
            $whatsappUrl = null;
        }

        return view('customer.order', [
            'order' => $order,
            'whatsappUrl' => $whatsappUrl,
            'receiptUrl' => $receiptUrl,
        ]);
    }

    public function receipt(
        string $orderCode,
        WhatsAppLinkService $whatsApp,
    ): View {
        $order = Order::query()
            ->with('items')
            ->where('order_code', $orderCode)
            ->firstOrFail();

        try {
            $whatsappUrl = $whatsApp->buildWhatsAppUrl($order, request()->fullUrl());
        } catch (InvalidArgumentException) {
            $whatsappUrl = null;
        }

        return view('customer.receipt', compact('order', 'whatsappUrl'));
    }
}
