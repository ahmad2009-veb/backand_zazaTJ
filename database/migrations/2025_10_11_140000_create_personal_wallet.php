<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Wallet;

return new class extends Migration
{
    public function up(): void
    {
        Wallet::firstOrCreate(
            ['name' => 'Личный'],
            [
                'logo' => 'assets/wallet_icons/personal.png',
                'is_available' => true,
                'vendor_id' => null,
                'type' => 'personal',
            ]
        );
    }

    public function down(): void
    {
        Wallet::where('name', 'Личный')->delete();
    }
};
