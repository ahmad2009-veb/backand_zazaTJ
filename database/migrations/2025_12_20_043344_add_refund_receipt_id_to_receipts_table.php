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
        Schema::table('receipts', function (Blueprint $table) {
            // Link to the original receipt being refunded
            $table->foreignId('original_receipt_id')->nullable()->after('warehouse_transfer_id')
                ->constrained('receipts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['original_receipt_id']);
            $table->dropColumn('original_receipt_id');
        });
    }
};
