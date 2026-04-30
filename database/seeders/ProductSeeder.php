<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $startTime = microtime(true); // Start time
        $productsTotal = 1000;
        $chunkSize = 10;
        $progressBar = $this->command->getOutput()->createProgressBar($productsTotal);

        $products =  Product::factory($productsTotal)->make()->toArray();
        $progressBar->start();

        DB::transaction(function () use ($products, $progressBar, $chunkSize) {
            foreach (array_chunk($products, $chunkSize) as $productsChunk) {
                Product::insert($productsChunk);
                $progressBar->advance($chunkSize);
            }
        });
        $progressBar->finish();
        $this->command->getOutput()->writeln('');
        $endTime = microtime(true); // End time
        $executionTime = $endTime - $startTime; // Calculate execution time
        $this->command->getOutput()->writeln("Execution time: " . round($executionTime, 2) . " seconds");
    }
}
