<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('logo')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['vendor_id', 'wallet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_wallets');
    }
};

