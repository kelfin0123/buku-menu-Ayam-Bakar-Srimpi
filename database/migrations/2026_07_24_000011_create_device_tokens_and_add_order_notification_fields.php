<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('token', 512)->unique();
            $table->string('platform', 30)->nullable();
            $table->string('device_name')->nullable();
            $table->string('role', 30)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('vibration_enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('new_order_notification_sent_at')->nullable()->index();
            $table->uuid('new_order_notification_id')->nullable()->unique();
            $table->boolean('is_seen')->default(false)->index();
            $table->timestamp('seen_at')->nullable();
            $table->string('seen_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'new_order_notification_sent_at',
                'new_order_notification_id',
                'is_seen',
                'seen_at',
                'seen_by',
            ]);
        });
        Schema::dropIfExists('device_tokens');
    }
};
