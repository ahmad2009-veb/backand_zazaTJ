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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('loyalty_points_percentage', 5, 2)->default(0.00)->after('source');
            $table->decimal('loyalty_points', 10, 2)->default(0.00)->after('loyalty_points_percentage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_percentage', 'loyalty_points']);
        });
    }
};
