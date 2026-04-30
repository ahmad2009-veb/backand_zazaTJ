<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\TransactionCategory;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SaleTransactionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categoryName = 'Реализация';
        $admins = Admin::all();
        foreach ($admins as   $admin) {
            TransactionCategory::query()->firstOrCreate([
                'admin_id'  => $admin->id,
                'name' => $categoryName
            ]);
        }
        $vendors = Vendor::all();
        foreach ($vendors as $vendor) {
            TransactionCategory::firstOrCreate([
                'vendor_id' => $vendor->id,
                'name' => $categoryName
            ]);
        }
    }
}
