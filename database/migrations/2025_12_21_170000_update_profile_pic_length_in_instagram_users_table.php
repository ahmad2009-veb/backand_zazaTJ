<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Instagram profile picture URLs can be very long (400+ characters)
     * due to CDN parameters and query strings.
     * Changing from VARCHAR(255) to TEXT to accommodate full URLs.
     */
    public function up(): void
    {
        Schema::table('instagram_users', function (Blueprint $table) {
            $table->text('profile_pic')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instagram_users', function (Blueprint $table) {
            $table->string('profile_pic', 255)->nullable()->change();
        });
    }
};
