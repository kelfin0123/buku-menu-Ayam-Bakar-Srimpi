<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const SHIPPING_COST = 5000;

    /**
     * Tampilkan halaman checkout. Data keranjang dikirim dari localStorage (JS)
     * lalu di-render ulang lewat form tersembunyi / fetch ke endpoint store().
     */
    public function index(): View
    {
        return view('customer.checkout', [
            'shippingCost' => self::SHIPPING_COST,
        ]);
    }

    /**
     * Simpan order dari data keranjang (JSON: [{product_id, qty}, ...]).
     * Tahap ini HANYA membuat record order (status pending), integrasi
     * Midtrans Snap token akan ditambahkan pada langkah berikutnya.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'required|string|max:20',
            'customer_address' => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'         => 'required|integer|min:1',
        ]);

        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $lineTotal = $product->final_price * $item['qty'];
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'price'        => $product->final_price,
                'qty'          => $item['qty'],
                'subtotal'     => $lineTotal,
            ];
        }

        $order = Order::create([
            'order_code'        => Order::generateOrderCode(),
            'customer_name'     => $validated['customer_name'],
            'customer_phone'    => $validated['customer_phone'],
            'customer_address'  => $validated['customer_address'] ?? null,
            'subtotal'          => $subtotal,
            'shipping_cost'     => self::SHIPPING_COST,
            'total'             => $subtotal + self::SHIPPING_COST,
            'payment_status'    => 'pending',
            'status'            => 'waiting',
        ]);

        foreach ($itemsData as $data) {
            $order->items()->create($data);
        }

        return redirect()
            ->route('order.show', $order->order_code)
            ->with('success', 'Pesanan berhasil dibuat!');
    }
}
