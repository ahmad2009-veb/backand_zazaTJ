<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connected_social_account_id');

            $table->string('sender_id'); // Facebook user PSID
            $table->string('recipient_id'); // Page ID
            $table->text('message');
            $table->enum('direction', ['in', 'out']); // in=входящее, out=исходящее
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index('connected_social_account_id');
            $table->index('sender_id');
            $table->index(['connected_social_account_id', 'sender_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_messages');
    }
};
