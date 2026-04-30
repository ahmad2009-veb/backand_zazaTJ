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
        Schema::table('arrivals', function (Blueprint $table) {
            Schema::table('arrivals', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
            });
    
            // Добавляем новый внешний ключ с CASCADE
            Schema::table('arrivals', function (Blueprint $table) {
                $table->foreign('warehouse_id')
                      ->references('id')
                      ->on('warehouses')
                      ->onDelete('cascade');
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('arrivals', function (Blueprint $table) {
            Schema::table('arrivals', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
            });
    
            // Восстанавливаем старый внешний ключ
            Schema::table('arrivals', function (Blueprint $table) {
                $table->foreign('warehouse_id')
                      ->references('id')
                      ->on('warehouses')
                      ->onDelete('no action');
            });
        });
    }
};
