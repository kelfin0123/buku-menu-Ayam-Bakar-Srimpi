<?php

namespace App\Services;

use App\Models\Order;
use InvalidArgumentException;

class WhatsAppLinkService
{
    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Nomor WhatsApp pelanggan belum tersedia.');
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (! preg_match('/^628[1-9][0-9]{7,11}$/', $digits)) {
            throw new InvalidArgumentException('Nomor WhatsApp pelanggan tidak valid.');
        }

        return $digits;
    }

    public function buildReceiptMessage(Order $order, ?string $receiptUrl = null): string
    {
        $items = $order->items->map(
            fn ($item) => sprintf(
                '%dx %s - %s',
                $item->qty,
                $item->product_name,
                $this->rupiah($item->subtotal),
            )
        )->implode("\n");

        $message = sprintf(
            "Halo %s 👋\n\n".
            "%s\n\n".
            "No. Transaksi: %s\n".
            "Tipe Pesanan: %s\n".
            "Tanggal: %s\n".
            "Metode Pembayaran: %s\n\n".
            "Detail Pesanan:\n%s\n\n".
            "Subtotal: %s\n".
            "Diskon: %s\n".
            "Pajak: %s\n".
            "Total: %s\n\n".
            'Status: %s',
            $order->customer_name ?: 'Pelanggan',
            $order->is_delivery
                ? 'Pesanan Anda telah dibuat di Ayam Bakar Srimpi.'
                : 'Terima kasih telah melakukan pemesanan di Ayam Bakar Srimpi.',
            $order->order_code,
            $order->is_delivery ? 'Pesan Antar' : 'Ambil Sendiri',
            $order->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            strtoupper((string) ($order->payment_method ?: '-')),
            $items,
            $this->rupiah($order->subtotal),
            $this->rupiah(0),
            $this->rupiah(0),
            $this->rupiah($order->total),
            ucfirst(str_replace('_', ' ', $order->status)),
        );

        if ($order->is_delivery) {
            $message .= sprintf(
                "\n\nAlamat Pengantaran:\n%s\n\nPatokan:\n%s".
                "\n\nOngkos Kirim: %s\nTotal Sementara: %s".
                "\n\nCatatan:\n%s",
                $order->delivery_address,
                $order->delivery_address_detail ?: '-',
                $order->delivery_fee_status === 'confirmed'
                    ? $this->rupiah($order->delivery_fee)
                    : 'Belum ditentukan',
                $this->rupiah($order->total),
                $order->delivery_note
                    ?: 'Ongkos kirim akan dikonfirmasi oleh kasir melalui WhatsApp.',
            );
        }

        if (filled($receiptUrl)) {
            $message .= "\n\nLihat nota digital:\n".$receiptUrl;
        }

        return $message."\n\nTerima kasih 🙏";
    }

    public function buildWhatsAppUrl(Order $order, ?string $receiptUrl = null): string
    {
        if (in_array($order->status, [
            Order::STATUS_CANCELLED,
            Order::STATUS_EXPIRED,
        ], true)) {
            throw new InvalidArgumentException('Nota pesanan ini tidak dapat dikirim.');
        }

        return sprintf(
            'https://wa.me/%s?text=%s',
            $this->normalizePhone($order->customer_phone),
            rawurlencode($this->buildReceiptMessage($order, $receiptUrl)),
        );
    }

    private function rupiah(int|float|string|null $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}
