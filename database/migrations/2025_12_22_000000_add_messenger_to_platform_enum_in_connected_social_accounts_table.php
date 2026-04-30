<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddMessengerToPlatformEnumInConnectedSocialAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // For MySQL, we need to alter the ENUM column to add 'messenger'
        DB::statement("ALTER TABLE connected_social_accounts MODIFY COLUMN platform ENUM('facebook', 'instagram', 'whatsapp', 'messenger') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove 'messenger' from the ENUM (only if no records use it)
        DB::statement("ALTER TABLE connected_social_accounts MODIFY COLUMN platform ENUM('facebook', 'instagram', 'whatsapp') NOT NULL");
    }
}
