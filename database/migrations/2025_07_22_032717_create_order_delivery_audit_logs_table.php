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
        Schema::create('order_delivery_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            $table->unsignedInteger('original_quantity');
            $table->unsignedInteger('new_quantity');
            $table->enum('action', ['created', 'updated']);
            $table->text('reason')->nullable();

            $table->foreignId('actor_id')->nullable()->constrained('users'); // vendor or courier
            $table->string('actor_role')->nullable(); // 'vendor', 'courier'

            $table->timestamp('logged_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_delivery_audit_logs');
    }
};
