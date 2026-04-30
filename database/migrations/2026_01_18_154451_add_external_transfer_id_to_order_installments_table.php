<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_installments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        
        Schema::table('order_installments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
        
        Schema::table('order_installments', function (Blueprint $table) {
            $table->foreignId('external_transfer_id')->nullable()->after('order_id')->constrained('warehouse_transfers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_installments', function (Blueprint $table) {
            $table->dropForeign(['external_transfer_id']);
            $table->dropColumn('external_transfer_id');
            
        });
    }
};
