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
        Schema::table('vendor_employees', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('employee_role_id');
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_employees', function (Blueprint $table) {
            $table->dropColumn('modules');
            $table->string('email')->nullable(false)->change();
        });
    }
};
