<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsSeenToMessengerMessagesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add is_seen to instagram_messages
        if (Schema::hasTable('instagram_messages') && !Schema::hasColumn('instagram_messages', 'is_seen')) {
            Schema::table('instagram_messages', function (Blueprint $table) {
                $table->boolean('is_seen')->default(0)->after('sent_at');
            });
        }

        // Add is_seen to whatsapp_messages
        if (Schema::hasTable('whatsapp_messages') && !Schema::hasColumn('whatsapp_messages', 'is_seen')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->boolean('is_seen')->default(0)->after('sent_at');
            });
        }

        // Add is_seen to messenger_messages
        if (Schema::hasTable('messenger_messages') && !Schema::hasColumn('messenger_messages', 'is_seen')) {
            Schema::table('messenger_messages', function (Blueprint $table) {
                $table->boolean('is_seen')->default(0)->after('sent_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('instagram_messages', 'is_seen')) {
            Schema::table('instagram_messages', function (Blueprint $table) {
                $table->dropColumn('is_seen');
            });
        }

        if (Schema::hasColumn('whatsapp_messages', 'is_seen')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropColumn('is_seen');
            });
        }

        if (Schema::hasColumn('messenger_messages', 'is_seen')) {
            Schema::table('messenger_messages', function (Blueprint $table) {
                $table->dropColumn('is_seen');
            });
        }
    }
}

