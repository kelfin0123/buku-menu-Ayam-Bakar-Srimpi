<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CheckExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and mark expired orders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredOrders = Order::where('expires_at', '<', now())
            ->where('status', '!=', Order::STATUS_EXPIRED)
            ->where('status', '!=', Order::STATUS_COMPLETED)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get();

        foreach ($expiredOrders as $order) {
            $order->markAsExpired();
            $this->info("Order {$order->order_code} marked as expired");
        }

        $this->info("Checked {$expiredOrders->count()} expired orders");

        return self::SUCCESS;
    }
}
