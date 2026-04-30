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
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('counterparty'); // Контрагент
            $table->string('name'); // Наименование
            $table->enum('type', ['employee', 'client', 'supplier', 'investor', 'bank', 'partner', 'other']); // Тип
            $table->decimal('balance', 24, 2)->default(0); // Баланс
            $table->text('comment')->nullable(); // Комментарий
            $table->enum('status', ['active', 'inactive'])->default('active'); // Статус
            $table->string('photo')->nullable(); // Photo
            $table->timestamps();
            
            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('counterparties');
    }
};
