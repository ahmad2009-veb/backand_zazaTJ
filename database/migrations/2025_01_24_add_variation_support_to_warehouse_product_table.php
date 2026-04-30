<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_product', function (Blueprint $table) {
            // Add variation support
            $table->string('variation_id')->nullable()->after('product_id');
            $table->string('attribute_value')->nullable()->after('variation_id');
            $table->unsignedBigInteger('attribute_id')->nullable()->after('attribute_value');
            $table->decimal('cost_price', 24, 2)->nullable()->after('quantity');
            $table->decimal('sale_price', 24, 2)->nullable()->after('cost_price');
            $table->string('barcode')->nullable()->after('sale_price');
            
            // Update primary key to composite if variations are used
            $table->index(['warehouse_id', 'product_id', 'variation_id']);
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_product', function (Blueprint $table) {
            $table->dropIndex(['warehouse_id', 'product_id', 'variation_id']);
            $table->dropIndex(['barcode']);
            $table->dropColumn([
                'variation_id',
                'attribute_value',
                'attribute_id',
                'cost_price',
                'sale_price',
                'barcode'
            ]);
        });
    }
};

