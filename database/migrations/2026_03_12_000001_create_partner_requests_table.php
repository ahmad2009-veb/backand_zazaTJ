<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->default('restaurant'); // 'restaurant' | 'b2b'

            // General
            $table->string('merchant_name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();

            // B2B specific
            $table->string('company_name')->nullable();
            $table->text('description')->nullable();

            // Meta
            $table->string('status', 20)->default('new'); // new | contacted | rejected | approved
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_requests');
    }
};
