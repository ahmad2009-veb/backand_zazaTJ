<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('points', 10, 2)->default(0);
            $table->json('restaurant_ids')->nullable();
            $table->foreignId('campaign_id')->nullable()->index()->references('id')->on('campaigns');
            $table->integer('order_id')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('loyalty_point_id')->nullable()->references('id')->on('loyalty_points');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_points');
    }
}
