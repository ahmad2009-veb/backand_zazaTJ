<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arrival_warehouse_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arrival_id')->constrained('arrivals')->onDelete('cascade');
            $table->foreignId('warehouse_product_id')->constrained('warehouse_product')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('arrival_warehouse_products');
    }
};
