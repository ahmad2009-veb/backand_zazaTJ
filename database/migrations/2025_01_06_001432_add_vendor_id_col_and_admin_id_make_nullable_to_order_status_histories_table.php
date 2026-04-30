<?php

use App\Models\Admin;
use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->foreignIdFor(Admin::class)->nullable()->change();
            $table->foreignIdFor(\App\Models\Vendor::class)
            ->nullable() // If vendor_id should be nullable, add this
            ->constrained('vendors') // Automatically references the `vendors` table
            ->cascadeOnDelete(); // Enforce cascading delete
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Admin::class)
            ->nullable(false)
            ->change();
         $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
