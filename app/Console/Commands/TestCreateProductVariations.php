<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestCreateProductVariations extends Command
{
    protected $signature = 'test:create-product-variations';
    protected $description = 'Test creating a product with variations';

    public function handle()
    {
        DB::transaction(function () {
            // Create product
            $product = Product::create([
                'name' => 'Test T-Shirt with Variations',
                'price' => 20,
                'purchase_price' => 15,
                'quantity' => 100,
                'category_id' => 1,
                'category_ids' => json_encode([
                    ['id' => 1, 'position' => 1],
                ]),
                'store_id' => 1,
                'warehouse_id' => 43,
                'discount' => 0,
                'discount_type' => 'amount',
                'description' => 'Test product with size variations',
                'variation_name' => 'Size',
                'status' => 1,
            ]);

            $this->info("✅ Product created: ID {$product->id}, Name: {$product->name}");

            // Create variations
            $variations = [
                [
                    'variation_id' => 'S_123456_0',
                    'attribute_value' => 'Small',
                    'attribute_id' => 1,
                    'cost_price' => 15,
                    'sale_price' => 20,
                    'quantity' => 30,
                    'barcode' => 'TSHIRT-S-001',
                ],
                [
                    'variation_id' => 'M_123456_1',
                    'attribute_value' => 'Medium',
                    'attribute_id' => 1,
                    'cost_price' => 16,
                    'sale_price' => 22,
                    'quantity' => 50,
                    'barcode' => 'TSHIRT-M-001',
                ],
                [
                    'variation_id' => 'L_123456_2',
                    'attribute_value' => 'Large',
                    'attribute_id' => 1,
                    'cost_price' => 17,
                    'sale_price' => 24,
                    'quantity' => 20,
                    'barcode' => 'TSHIRT-L-001',
                ],
            ];

            foreach ($variations as $var) {
                ProductVariation::create(array_merge(['product_id' => $product->id], $var));
                $this->info("✅ Variation created: {$var['attribute_value']} (Qty: {$var['quantity']})");
            }

            // Verify
            $product->refresh();
            $allVariations = $product->variations()->get();
            $totalQty = $allVariations->sum('quantity');

            $this->info("\n📊 Summary:");
            $this->info("Product ID: {$product->id}");
            $this->info("Product Name: {$product->name}");
            $this->info("Warehouse ID: {$product->warehouse_id}");
            $this->info("Total Variations: {$allVariations->count()}");
            $this->info("Total Quantity: {$totalQty}");
            $this->info("\nVariations:");
            foreach ($allVariations as $var) {
                $this->info("  - {$var->attribute_value}: {$var->quantity} units @ {$var->sale_price} (Cost: {$var->cost_price})");
            }
        });

        $this->info("\n✅ Test completed successfully!");
    }
}

