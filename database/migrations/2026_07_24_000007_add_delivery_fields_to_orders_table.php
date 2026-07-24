<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('is_delivery')->default(false)->after('customer_address');
            $table->string('order_type')->nullable()->after('is_delivery');
            $table->text('delivery_address')->nullable()->after('order_type');
            $table->text('delivery_address_detail')->nullable()->after('delivery_address');
            $table->text('delivery_note')->nullable()->after('delivery_address_detail');
            $table->decimal('delivery_fee', 12, 2)->nullable()->after('shipping_cost');
            $table->string('delivery_fee_status')->nullable()->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'is_delivery',
                'order_type',
                'delivery_address',
                'delivery_address_detail',
                'delivery_note',
                'delivery_fee',
                'delivery_fee_status',
            ]);
        });
    }
};
