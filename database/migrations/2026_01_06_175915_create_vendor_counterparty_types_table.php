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
        Schema::create('vendor_counterparty_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('value'); // Custom type value (auto-generated from label)
            $table->string('label'); // Display label (can be in different language)
            $table->timestamps();
            
            $table->index(['vendor_id']);
            $table->unique(['vendor_id', 'value']); // Prevent duplicate values per vendor
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vendor_counterparty_types');
    }
};
