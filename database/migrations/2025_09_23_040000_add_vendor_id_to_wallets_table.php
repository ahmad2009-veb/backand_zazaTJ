<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->after('is_available');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            
            
            $table->index(['vendor_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id', 'is_available']);
            $table->dropColumn('vendor_id');
        });
    }
};
