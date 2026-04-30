<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->date('purchase_date');
            $table->text('products');
            $table->integer('total_order_count');
            $table->decimal('total_order_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('size')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'store_id']);
            $table->index('purchase_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_imports');
    }
};
