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
        // Drop foreign keys first (if they exist)
        try {
            Schema::table('vendor_employees', function (Blueprint $table) {
                $table->dropForeign(['employee_role_id']);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist, continue
        }

        try {
            Schema::table('vendor_employees', function (Blueprint $table) {
                $table->dropForeign(['restaurant_id']);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist, continue
        }

        try {
            Schema::table('vendor_employees', function (Blueprint $table) {
                $table->dropForeign(['store_id']);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist, continue
        }

        // Drop the columns if they exist
        Schema::table('vendor_employees', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_employees', 'employee_role_id')) {
                $table->dropColumn('employee_role_id');
            }
            if (Schema::hasColumn('vendor_employees', 'restaurant_id')) {
                $table->dropColumn('restaurant_id');
            }
            if (Schema::hasColumn('vendor_employees', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_employees', function (Blueprint $table) {
            $table->foreignId('employee_role_id')->nullable()->constrained('employee_roles');
            $table->foreignId('restaurant_id')->nullable()->constrained('restaurants');
            $table->foreignId('store_id')->nullable()->constrained('stores');
        });
    }
};
