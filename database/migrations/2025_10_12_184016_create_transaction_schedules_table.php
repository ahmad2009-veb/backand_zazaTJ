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
        Schema::create('transaction_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained('counterparties')->cascadeOnDelete();
            $table->foreignId('transaction_category_id')->constrained('transaction_categories')->cascadeOnDelete();
            $table->enum('transaction_type', ['income', 'expense', 'dividends']);
            $table->decimal('amount', 24, 2);
            $table->enum('cycle_type', ['one_time', 'weekly', 'monthly']);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active'); //for future use cases if we need that
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['created_at', 'cycle_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_schedules');
    }
};
