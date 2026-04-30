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
        Schema::table('counterparties', function (Blueprint $table) {
      
            $table->string('address')->nullable()->after('name');
            $table->string('requisite')->nullable()->after('address');
            $table->string('phone')->nullable()->after('requisite');
            
          
            $table->renameColumn('comment', 'notes');
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['address', 'requisite', 'phone']);
            
            // Rename notes back to comment
            $table->renameColumn('notes', 'comment');
        });
    }
};

