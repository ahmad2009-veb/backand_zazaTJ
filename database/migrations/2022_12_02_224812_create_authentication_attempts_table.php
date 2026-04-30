<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthenticationAttemptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('authentication_attempts', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('attempts');
            $table->string('phone', 9);
            $table->timestamp('expire');
            $table->smallInteger('code')->nullable();
            $table->string('user_agent');
            $table->ipAddress('ip');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('authentication_attempts');
    }
}
