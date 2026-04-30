<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            Schema::drop('wallet_transactions');
        }
    }

    public function down(): void
    {
        // no-op: legacy table intentionally not recreated
    }
};

