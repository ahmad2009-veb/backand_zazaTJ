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
        Schema::table('customer_imports', function (Blueprint $table) {
            $table->date('purchase_date')->nullable()->change();
            $table->text('products')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_imports', function (Blueprint $table) {
            $table->date('purchase_date')->nullable(false)->change();
            $table->text('products')->nullable(false)->change();
        });
    }
};
