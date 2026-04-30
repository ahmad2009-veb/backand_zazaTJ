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
        Schema::table('sales', function (Blueprint $table) {
            // Make user_id nullable for vendor-to-vendor sales
            $table->foreignId('user_id')->nullable()->change();
            
            // Add vendor_id for external transfers (vendor-to-vendor sales)
            $table->foreignId('vendor_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            
            // Add warehouse_transfer_id to link sale to transfer
            $table->foreignId('warehouse_transfer_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
            
            $table->dropForeign(['warehouse_transfer_id']);
            $table->dropColumn('warehouse_transfer_id');
            
            // Revert user_id to non-nullable (be careful - this may fail if nulls exist)
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};

