<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::getDatabaseName();

        $ops = [
            ['vendor_employees','employee_number',['vendor_id','employee_number']],
            ['orders','order_number',['store_id','order_number']],
            ['order_status_histories','status_history_number',['vendor_id','status_history_number']],
            ['stores','store_number',['vendor_id','store_number']],
            ['transaction_categories','category_number',['vendor_id','category_number']],
            ['user_infos','user_number',['vendor_id','user_number']],
            ['withdrawal_requests','withdrawal_number',['vendor_id','withdrawal_number']],
            ['users','user_number',['created_by','user_number']],
            ['transactions','transaction_number',['vendor_id','transaction_number']],
            ['delivery_men','delivery_man_number',['store_id','delivery_man_number']],
        ];

        foreach ($ops as [$table,$col,$idxCols]) {
            if (!Schema::hasTable($table)) continue;

            // Add column if missing
            if (!Schema::hasColumn($table,$col)) {
                Schema::table($table,function(Blueprint $t) use ($col){
                    $t->integer($col)->nullable()->after('id');
                });
            }

            // Add index if missing
            $idxName = $table.'_'.implode('_',$idxCols).'_index';
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema',$db)
                ->where('table_name',$table)
                ->where('index_name',$idxName)
                ->exists();

            $allColsExist = collect($idxCols)->every(fn($c)=>Schema::hasColumn($table,$c));
            if (!$exists && $allColsExist) {
                Schema::table($table,function(Blueprint $t) use ($idxCols){
                    $t->index($idxCols);
                });
            }
        }
    }

    public function down(): void
    {
        $ops = [
            ['vendor_employees','employee_number',['vendor_id','employee_number']],
            ['orders','order_number',['store_id','order_number']],
            ['order_status_histories','status_history_number',['vendor_id','status_history_number']],
            ['stores','store_number',['vendor_id','store_number']],
            ['transaction_categories','category_number',['vendor_id','category_number']],
            ['user_infos','user_number',['vendor_id','user_number']],
            ['withdrawal_requests','withdrawal_number',['vendor_id','withdrawal_number']],
            ['users','user_number',['created_by','user_number']],
            ['transactions','transaction_number',['vendor_id','transaction_number']],
            ['delivery_men','delivery_man_number',['store_id','delivery_man_number']],
        ];

        foreach ($ops as [$table,$col,$idxCols]) {
            if (!Schema::hasTable($table)) continue;

            $idxName = $table.'_'.implode('_',$idxCols).'_index';

            Schema::table($table,function(Blueprint $t) use ($col,$idxName){
                if (Schema::hasColumn($t->getTable(),$col)) {
                    $t->dropColumn($col);
                }
                $t->dropIndex($idxName);
            });
        }
    }
};
