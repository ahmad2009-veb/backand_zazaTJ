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
        Schema::table('campaign_items', function (Blueprint $table) {
            $table->foreignId('food_id')->constrained('food');
            $table->smallInteger('quantity')->default(1);
            $table->string('variant')->nullable();
            $table->json('add_ons')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('campaign_items', function (Blueprint $table) {
            $table->dropForeign(['food_id']);
            $table->dropColumn('food_id');
            $table->dropColumn('quantity');
            $table->dropColumn('variant');
            $table->dropColumn('add_ons');
        });
    }
};
