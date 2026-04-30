<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstagramMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('instagram_messages', function (Blueprint $table) {
            $table->id();
            $table->integer('connected_social_account_id');

            $table->string('sender_id'); // Instagram user ID
            $table->string('recipient_id'); // usually our ig_business_id
            $table->text('message'); // text content
            $table->enum('direction', ['in', 'out']); // in=поступило, out=отправлено
            $table->timestamp('sent_at')->nullable();

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
        Schema::dropIfExists('instagram_messages');
    }
}
