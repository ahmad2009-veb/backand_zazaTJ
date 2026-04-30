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
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('restaurant_discount_amount', 'store_discount_amount');
            $table->renameColumn('comment_for_restaurant', 'comment_for_store');
            $table->renameColumn('total_foods_price', 'total_products_price');
            $table->renameColumn('restaurant_id', 'store_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('store_discount_amount', 'restaurant_discount_amount');
            $table->renameColumn('comment_for_store', 'comment_for_restaurant');
            $table->renameColumn('total_products_price', 'total_foods_price');
            $table->renameColumn('store_id', 'restaurant_id');
        });
    }
};
