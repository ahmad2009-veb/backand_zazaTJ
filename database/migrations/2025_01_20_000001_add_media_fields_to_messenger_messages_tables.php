<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMediaFieldsToMessengerMessagesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add media fields to instagram_messages
        if (Schema::hasTable('instagram_messages')) {
            Schema::table('instagram_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('instagram_messages', 'media_type')) {
                    $table->string('media_type')->nullable()->after('message'); // image, video, audio, document
                }
                if (!Schema::hasColumn('instagram_messages', 'media_url')) {
                    $table->text('media_url')->nullable()->after('media_type');
                }
                if (!Schema::hasColumn('instagram_messages', 'media_id')) {
                    $table->string('media_id')->nullable()->after('media_url'); // Facebook attachment ID
                }
            });
        }

        // Add media fields to whatsapp_messages
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_messages', 'media_type')) {
                    $table->string('media_type')->nullable()->after('message');
                }
                if (!Schema::hasColumn('whatsapp_messages', 'media_url')) {
                    $table->text('media_url')->nullable()->after('media_type');
                }
                if (!Schema::hasColumn('whatsapp_messages', 'media_id')) {
                    $table->string('media_id')->nullable()->after('media_url');
                }
            });
        }

        // Add media fields to messenger_messages
        if (Schema::hasTable('messenger_messages')) {
            Schema::table('messenger_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('messenger_messages', 'media_type')) {
                    $table->string('media_type')->nullable()->after('message');
                }
                if (!Schema::hasColumn('messenger_messages', 'media_url')) {
                    $table->text('media_url')->nullable()->after('media_type');
                }
                if (!Schema::hasColumn('messenger_messages', 'media_id')) {
                    $table->string('media_id')->nullable()->after('media_url');
                }
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
        if (Schema::hasColumn('instagram_messages', 'media_type')) {
            Schema::table('instagram_messages', function (Blueprint $table) {
                $table->dropColumn(['media_type', 'media_url', 'media_id']);
            });
        }

        if (Schema::hasColumn('whatsapp_messages', 'media_type')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropColumn(['media_type', 'media_url', 'media_id']);
            });
        }

        if (Schema::hasColumn('messenger_messages', 'media_type')) {
            Schema::table('messenger_messages', function (Blueprint $table) {
                $table->dropColumn(['media_type', 'media_url', 'media_id']);
            });
        }
    }
}

