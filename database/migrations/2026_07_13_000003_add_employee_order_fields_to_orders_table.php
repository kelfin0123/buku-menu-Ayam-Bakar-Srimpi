<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('table_number')->nullable()->after('customer_address');
            $table->unsignedBigInteger('employee_id')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('employee_id');
            $table->timestamp('finished_at')->nullable()->after('accepted_at');
            $table->timestamp('rejected_at')->nullable()->after('finished_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['table_number', 'employee_id', 'accepted_at', 'finished_at', 'rejected_at', 'rejection_reason']);
        });
    }
};
