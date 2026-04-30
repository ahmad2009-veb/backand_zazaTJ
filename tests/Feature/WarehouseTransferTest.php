<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;
    protected Store $store;
    protected Warehouse $fromWarehouse;
    protected Warehouse $toWarehouse;
    protected Product $product;
    protected ProductVariation $variation2kg;
    protected ProductVariation $variation5kg;

    protected function setUp(): void
    {
        parent::setUp();

        // Create vendor
        $this->vendor = Vendor::factory()->create();

        // Create store for vendor
        $this->store = Store::create([
            'name' => 'Test Store',
            'vendor_id' => $this->vendor->id,
            'address' => 'Test Address',
            'phone' => '+992123456789',
            'email' => 'store@test.com',
        ]);

        // Create warehouses (linked to store, not vendor)
        $this->fromWarehouse = Warehouse::factory()->create(['store_id' => $this->store->id]);
        $this->toWarehouse = Warehouse::factory()->create(['store_id' => $this->store->id]);

        // Create product with variations
        $this->product = Product::factory()->create([
            'name' => 'MASS3000',
            'variation_name' => 'WeightClass',
            'warehouse_id' => $this->fromWarehouse->id,
            'store_id' => $this->store->id,
        ]);

        // Create variations for the product
        $this->variation2kg = ProductVariation::factory()->create([
            'product_id' => $this->product->id,
            'variation_id' => '2kg_1234567890_0',
            'attribute_value' => '2000G',
            'cost_price' => 100,
            'sale_price' => 200,
            'quantity' => 50,
        ]);

        $this->variation5kg = ProductVariation::factory()->create([
            'product_id' => $this->product->id,
            'variation_id' => '5kg_1234567890_1',
            'attribute_value' => '5000G',
            'cost_price' => 200,
            'sale_price' => 300,
            'quantity' => 50,
        ]);
    }

    /**
     * Test that transfer items with different variations of same product are grouped together
     */
    public function test_transfer_groups_same_product_with_different_variations(): void
    {
        // Create transfer
        $transfer = WarehouseTransfer::create([
            'vendor_id' => $this->vendor->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_number' => 'TRF-TEST-001',
            'transfer_type' => 'internal',
            'status' => 'pending',
        ]);

        // Add same product with 2kg variation
        WarehouseTransferItem::create([
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'product_variation_id' => $this->variation2kg->id,
            'quantity' => 1,
        ]);

        // Add same product with 5kg variation
        WarehouseTransferItem::create([
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'product_variation_id' => $this->variation5kg->id,
            'quantity' => 2,
        ]);

        // Load transfer with items
        $transfer->load(['items.product', 'items.productVariation', 'fromWarehouse', 'toWarehouse']);

        // Get resource response
        $resource = new \App\Http\Resources\WarehouseTransferResource($transfer);
        $response = $resource->toArray(request());

        // Assert products are grouped
        $this->assertArrayHasKey('products', $response);
        $this->assertCount(1, $response['products'], 'Same product should be grouped into one');

        // Assert the product has 2 variations
        $productData = $response['products'][0];
        $this->assertEquals($this->product->id, $productData['id']);
        $this->assertEquals('MASS3000', $productData['name']);
        $this->assertEquals('WeightClass', $productData['variation_name']);
        $this->assertCount(2, $productData['variations'], 'Product should have 2 variations');

        // Assert variation types are correct and different
        $variationTypes = array_column($productData['variations'], 'variation_type');
        $this->assertContains('2kg', $variationTypes);
        $this->assertContains('5kg', $variationTypes);

        // Assert quantities are correct
        $variation2kgData = collect($productData['variations'])->firstWhere('variation_type', '2kg');
        $variation5kgData = collect($productData['variations'])->firstWhere('variation_type', '5kg');

        $this->assertEquals(1, $variation2kgData['quantity']);
        $this->assertEquals(2, $variation5kgData['quantity']);

        // Assert prices are correct
        $this->assertEquals(100, $variation2kgData['cost_price']);
        $this->assertEquals(200, $variation2kgData['sale_price']);
        $this->assertEquals(200, $variation5kgData['cost_price']);
        $this->assertEquals(300, $variation5kgData['sale_price']);

        // Assert totals are correct
        $this->assertEquals(100, $variation2kgData['total_cost_price']); // 100 * 1
        $this->assertEquals(200, $variation2kgData['total_sale_price']); // 200 * 1
        $this->assertEquals(400, $variation5kgData['total_cost_price']); // 200 * 2
        $this->assertEquals(600, $variation5kgData['total_sale_price']); // 300 * 2

        // Assert overall totals
        $this->assertEquals(3, $response['total_quantity']); // 1 + 2
        $this->assertEquals(500, $response['total_cost_price']); // 100 + 400
        $this->assertEquals(800, $response['total_sale_price']); // 200 + 600
    }

    /**
     * Test that variation_type is correctly extracted from variation_id
     */
    public function test_variation_type_is_extracted_from_variation_id(): void
    {
        $transfer = WarehouseTransfer::create([
            'vendor_id' => $this->vendor->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_number' => 'TRF-TEST-002',
            'transfer_type' => 'internal',
            'status' => 'pending',
        ]);

        WarehouseTransferItem::create([
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'product_variation_id' => $this->variation5kg->id,
            'quantity' => 1,
        ]);

        $transfer->load(['items.product', 'items.productVariation', 'fromWarehouse', 'toWarehouse']);

        $resource = new \App\Http\Resources\WarehouseTransferResource($transfer);
        $response = $resource->toArray(request());

        $variationData = $response['products'][0]['variations'][0];

        // variation_id is "5kg_1234567890_1", so variation_type should be "5kg"
        $this->assertEquals('5kg', $variationData['variation_type']);
        $this->assertEquals('5kg_1234567890_1', $variationData['variation_id']);
    }
}

