<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProductsToVariations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:migrate-to-variations {--force : Force migration without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing products to new variation-based system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('This will migrate all products to the new variation system. Continue?')) {
            $this->info('Migration cancelled.');
            return;
        }

        DB::transaction(function () {
            $products = Product::all();
            $count = 0;

            foreach ($products as $product) {
                // Skip if already has variations
                if (ProductVariation::where('product_id', $product->id)->exists()) {
                    continue;
                }

                // If product has quantity, create a default variation
                if ($product->quantity > 0) {
                    ProductVariation::create([
                        'product_id' => $product->id,
                        'variation_id' => 'default_' . $product->id,
                        'attribute_value' => 'Default',
                        'cost_price' => $product->purchase_price,
                        'sale_price' => $product->price,
                        'quantity' => $product->quantity,
                        'barcode' => $product->product_code,
                    ]);
                    $count++;
                }
            }

            $this->info("Successfully migrated {$count} products to variations system.");
        });
    }
}

