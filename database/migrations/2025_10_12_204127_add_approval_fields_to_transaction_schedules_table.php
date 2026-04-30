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
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->boolean('requires_approval')->default(true); // Whether this schedule needs approval
            $table->json('approved_dates')->nullable(); // Store approved dates as JSON array
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
            $table->dropColumn(['wallet_id', 'requires_approval', 'approved_dates']);
        });
    }
};
