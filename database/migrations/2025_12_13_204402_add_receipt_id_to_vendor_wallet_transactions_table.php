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
        Schema::table('vendor_wallet_transactions', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->after('order_id')->constrained('receipts')->nullOnDelete();
            $table->index(['receipt_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vendor_wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['receipt_id']);
            $table->dropIndex(['receipt_id']);
            $table->dropColumn('receipt_id');
        });
    }
};
