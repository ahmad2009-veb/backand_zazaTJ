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
        Schema::table('counterparties', function (Blueprint $table) {
            $table->foreignId('vendor_reference_id')->nullable()->after('vendor_id')->constrained('vendors')->nullOnDelete();
            $table->index(['vendor_id', 'vendor_reference_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropForeign(['vendor_reference_id']);
            $table->dropIndex(['vendor_id', 'vendor_reference_id', 'type']);
            $table->dropColumn('vendor_reference_id');
        });
    }
};
