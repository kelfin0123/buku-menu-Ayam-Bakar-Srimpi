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
    /**
     * Tampilkan halaman checkout. Data keranjang dikirim dari localStorage (JS)
     * lalu dikirim ke API endpoint.
     */
    public function index(): View
    {
        return view('customer.checkout');
    }

    /**
     * Simpan pesanan dari data keranjang (JSON: [{product_id, qty}, ...]).
     * Order dibuat dengan status waiting_payment dan expires_at 5 menit.
     */
    public function store(Request $request): RedirectResponse
    {
        // Log incoming request for debugging
        \Log::info('Checkout Request:', [
            'items' => $request->input('items'),
            'customer_name' => $request->input('customer_name'),
            'table_number' => $request->input('table_number'),
            'payment_method' => $request->input('payment_method'),
        ]);

        $validated = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'table_number'     => 'required|string|max:50',
            'payment_method'   => 'required|in:cash,qris',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,firestore_id',
            'items.*.qty'         => 'required|integer|min:1',
        ]);

        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            \Log::info('Looking up product:', ['product_id' => $item['product_id']]);
            $product = Product::where('firestore_id', $item['product_id'])->firstOrFail();
            \Log::info('Product found:', ['id' => $product->id, 'firestore_id' => $product->firestore_id, 'name' => $product->name]);
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

        // Buat order dengan transaksi database
        \DB::beginTransaction();
        try {
            $order = Order::create([
                'order_code'        => Order::generateOrderCode(),
                'customer_name'     => $validated['customer_name'],
                'customer_phone'    => null,
                'customer_address'  => null,
                'table_number'      => $validated['table_number'],
                'subtotal'          => $subtotal,
                'shipping_cost'     => 0,
                'total'             => $subtotal,
                'payment_method'    => $validated['payment_method'],
                'payment_status'    => 'pending',
                'status'            => 'waiting_payment',
                'expires_at'        => now()->addMinutes(5),
            ]);

            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            \DB::commit();

            // Clear cart from localStorage by setting a session flag
            session(['clear_cart' => true]);

            return redirect()
                ->route('checkout.payment', $order->order_code)
                ->with('success', 'Pesanan berhasil dibuat!');

        } catch (\Exception $e) {
            \DB::rollBack();
            return back()
                ->withInput()
                ->withErrors(['error' => 'Terjadi kesalahan saat membuat pesanan. Silakan coba lagi.']);
        }
    }

    /**
     * Redirect to payment page after checkout
     */
    public function payment(Request $request, string $orderCode): View
    {
        return view('customer.payment', [
            'orderCode' => $orderCode,
        ]);
    }

    /**
     * Cek status pembayaran (AJAX)
     */
    public function status(Request $request, string $orderCode)
    {
        $order = Order::where('order_code', $orderCode)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_code' => $order->order_code,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'is_expired' => $order->isExpired(),
            ],
        ]);
    }

    /**
     * Batalkan pesanan
     */
    public function cancel(Request $request, string $orderCode): RedirectResponse
    {
        $order = Order::where('order_code', $orderCode)->first();

        if (!$order) {
            return back()->withErrors(['error' => 'Pesanan tidak ditemukan']);
        }

        if ($order->status === 'completed' || $order->status === 'cancelled') {
            return back()->withErrors(['error' => 'Pesanan tidak dapat dibatalkan']);
        }

        \DB::beginTransaction();
        try {
            $order->status = 'cancelled';
            $order->payment_status = 'failed';
            $order->save();

            \DB::commit();

            return redirect()
                ->route('home')
                ->with('success', 'Pesanan berhasil dibatalkan.');

        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat membatalkan pesanan.']);
        }
    }
}
