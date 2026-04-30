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
            // Make counterparty_id nullable (for vendor-to-vendor transfers)
            $table->foreignId('counterparty_id')->nullable()->change();

            // Add recipient_vendor_id for vendor-to-vendor transfers
            $table->foreignId('recipient_vendor_id')->nullable()->after('counterparty_id')
                ->constrained('vendors')->nullOnDelete();

            // Add warehouse_transfer_id to link receipt to transfer
            $table->foreignId('warehouse_transfer_id')->nullable()->after('recipient_vendor_id')
                ->constrained('warehouse_transfers')->nullOnDelete();
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
            $table->dropForeign(['recipient_vendor_id']);
            $table->dropColumn('recipient_vendor_id');

            $table->dropForeign(['warehouse_transfer_id']);
            $table->dropColumn('warehouse_transfer_id');

            // Revert counterparty_id to required
            $table->foreignId('counterparty_id')->nullable(false)->change();
        });
    }
};
