<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCampaignItemsDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('campaign_items_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_items_id')->constrained()->onDelete('cascade');
            $table->foreignId('food_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 8, 2)->default(0);
            $table->smallInteger('quantity')->default(1);
            $table->string('variant')->nullable();
            $table->json('add_ons')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index('campaign_items_id');
            $table->index('food_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('campaign_items_details');
    }
}
