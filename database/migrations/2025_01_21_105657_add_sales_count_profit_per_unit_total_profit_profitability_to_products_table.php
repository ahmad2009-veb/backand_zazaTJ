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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->decimal('profit_per_unit', 24, 2)->default(0);
            $table->decimal('total_revenue', 24, 2)->default(0);
            $table->decimal('total_profit', 24, 2)->default(0);
            $table->decimal('profitability', 5, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sales_count', 'profit_per_unit', 'total_profit', 'profitability', 'total_revenue']);
        });
    }
};
