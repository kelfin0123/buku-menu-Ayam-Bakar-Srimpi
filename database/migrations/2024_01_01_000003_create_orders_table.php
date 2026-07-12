<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique(); // ex: ABS-20260712-0001
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('customer_address')->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('shipping_cost')->default(0);
            $table->unsignedInteger('total');
            $table->string('payment_method')->nullable();   // midtrans channel
            $table->string('payment_status')->default('pending'); // pending/paid/failed
            $table->string('midtrans_snap_token')->nullable();
            $table->string('midtrans_order_id')->nullable();
            $table->string('status')->default('waiting'); // waiting/processed/delivered/cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
