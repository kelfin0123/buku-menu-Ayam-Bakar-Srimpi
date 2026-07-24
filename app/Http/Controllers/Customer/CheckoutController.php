<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirestoreOrderService;
use App\Services\OrderProductResolver;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderProductResolver $productResolver,
        private readonly FirestoreOrderService $firestoreOrders,
        private readonly WhatsAppLinkService $whatsApp,
    ) {}

    /**
     * Tampilkan halaman checkout. Data keranjang dikirim dari localStorage (JS)
     * lalu dikirim ke API endpoint.
     */
    public function index(): View
    {
        return view('customer.checkout');
    }

    /**
     * Simpan pesanan dari data keranjang.
     * Customer hanya mengirim customer_name, table_number, dan items[].
     * Laravel mengonversi firestore_id ke products.id secara internal.
     */
    public function store(Request $request): RedirectResponse
    {
        $isDelivery = $request->boolean('is_delivery');
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'string',
                'max:20',
                'regex:/^(?:\+?62|0|8)[0-9\s-]{8,17}$/',
            ],
            'table_number' => [
                Rule::requiredIf(! $isDelivery),
                'nullable',
                'string',
                'max:50',
            ],
            'is_delivery' => 'nullable|boolean',
            'delivery_address' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'string',
                'max:500',
            ],
            'delivery_address_detail' => 'nullable|string|max:250',
            'delivery_note' => 'nullable|string|max:250',
            'items' => 'required|array|min:1',
            'items.*.firestore_id' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);
        $customerPhone = filled($validated['customer_phone'] ?? null)
            ? $this->whatsApp->normalizePhone($validated['customer_phone'])
            : '';

        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = $this->productResolver->resolve($item['firestore_id']);

            if (! $product) {
                return back()
                    ->withInput()
                    ->withErrors(['items' => "Produk {$item['firestore_id']} tidak ditemukan atau tidak aktif di Firebase."]);
            }
            $lineTotal = $product->final_price * $item['qty'];
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $product->final_price,
                'qty' => $item['qty'],
                'subtotal' => $lineTotal,
            ];
        }

        \DB::beginTransaction();
        try {
            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $customerPhone,
                'customer_address' => null,
                'is_delivery' => $isDelivery,
                'order_type' => $isDelivery ? 'delivery' : 'pickup',
                'delivery_address' => $isDelivery ? $validated['delivery_address'] : null,
                'delivery_address_detail' => $isDelivery ? ($validated['delivery_address_detail'] ?? null) : null,
                'delivery_note' => $isDelivery ? ($validated['delivery_note'] ?? null) : null,
                'table_number' => $validated['table_number'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'delivery_fee' => null,
                'delivery_fee_status' => $isDelivery ? 'pending' : null,
                'total' => $subtotal,
                'payment_method' => null,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'status' => Order::STATUS_WAITING_PAYMENT,
                'expires_at' => now()->addMinutes(15),
            ]);

            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            \DB::commit();

            $this->firestoreOrders->sync($order->fresh('items.product'));

            session(['clear_cart' => true]);

            return redirect()->route('order.show', $order->order_code)
                ->with('success', 'Pesanan berhasil dikirim!');
        } catch (\Exception $e) {
            \DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['error' => 'Terjadi kesalahan saat membuat pesanan. Silakan coba lagi.']);
        }
    }

    public function selectPayment(Request $request, string $orderCode): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,qris,bank_transfer'],
        ]);
        $order = Order::where('order_code', $orderCode)->firstOrFail();

        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_EXPIRED], true)) {
            return back()->withErrors(['payment_method' => 'Pesanan tidak dapat dibayar.']);
        }

        $method = $validated['payment_method'];
        if ($order->is_delivery && $method === Order::PAYMENT_METHOD_CASH) {
            return back()->withErrors([
                'payment_method' => 'Pesanan antar hanya dapat dibayar melalui QR atau Transfer Bank.',
            ]);
        }
        $order->update([
            'payment_method' => $method,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => in_array($method, [
                Order::PAYMENT_METHOD_CASH,
                Order::PAYMENT_METHOD_BANK_TRANSFER,
            ], true)
                ? Order::STATUS_NEW_ORDER
                : Order::STATUS_WAITING_PAYMENT,
            'expires_at' => now()->addMinutes($method === Order::PAYMENT_METHOD_CASH ? 60 : 15),
        ]);
        $this->firestoreOrders->sync($order->fresh('items.product'));

        return $method === Order::PAYMENT_METHOD_BANK_TRANSFER
            ? redirect()->route('order.show', $order->order_code)
            : redirect()->route('checkout.payment', $order->order_code);
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

        if (! $order) {
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

        if (! $order) {
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

            return redirect('/')
                ->with('success', 'Pesanan berhasil dibatalkan.');

        } catch (\Exception $e) {
            \DB::rollBack();

            return back()->withErrors(['error' => 'Terjadi kesalahan saat membatalkan pesanan.']);
        }
    }
}
