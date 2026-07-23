<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = collect(['cost_price', 'stock', 'minimum_stock', 'barcode'])
            ->reject(fn (string $column): bool => Schema::hasColumn('products', $column))
            ->all();

        Schema::table('products', function (Blueprint $table) use ($missing) {
            if (in_array('cost_price', $missing, true)) {
                $table->unsignedInteger('cost_price')->default(0);
            }
            if (in_array('stock', $missing, true)) {
                $table->integer('stock')->default(0);
            }
            if (in_array('minimum_stock', $missing, true)) {
                $table->integer('minimum_stock')->default(5);
            }
            if (in_array('barcode', $missing, true)) {
                $table->string('barcode')->default('');
            }
        });
    }

    public function down(): void
    {
        foreach (['cost_price', 'stock', 'minimum_stock', 'barcode'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
