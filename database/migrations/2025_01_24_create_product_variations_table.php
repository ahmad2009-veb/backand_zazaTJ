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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('variation_id')->nullable();
            $table->string('attribute_value')->nullable(); 
            $table->unsignedBigInteger('attribute_id')->nullable();
            $table->decimal('cost_price', 24, 2)->nullable(); 
            $table->decimal('sale_price', 24, 2)->nullable(); 
            $table->decimal('quantity', 10, 2)->default(0); 
            $table->string('barcode')->nullable(); 
            $table->timestamps();
            
            $table->index(['product_id', 'variation_id']);
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};

