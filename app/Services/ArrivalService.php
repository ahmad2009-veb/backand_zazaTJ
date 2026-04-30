<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ArrivalService
{
    public function createNewProductArrival($productData)
    {
        $product   = Product::query()->find($productData['product_id']);
        $newCopiedProduct = $product->replicate();
        $newCopiedProduct->product_code = $productData['product_code'];
        $newCopiedProduct->quantity = $productData['quantity'];
        $newCopiedProduct->purchase_price = $productData['purchase_price'];
        $newCopiedProduct->price = $productData['retail_price'];
        $newCopiedProduct->save();
        if ($product->image) {
            $this->handleImageCopy($product, $newCopiedProduct);
        }

        return $newCopiedProduct;
    }

    public function checkProductCodeSame(int $product_id, ?string $product_code)
    {
        $product =  Product::query()->find($product_id)->product_code === $product_code;
        return $product;
    }


    public function handleImageCopy(Product $product, Product $newCopiedProduct)
    {
        $productFileName = $product->image;
        $productFilePath =  "public/product/$productFileName";

        $newFileName = now()->format('Y-m-d') . '-' . uniqid() . '.png';
        $newFilePath = "public/product/$newFileName"; // New file path
        if (Storage::exists($productFilePath)) {
            Storage::copy($productFilePath, $newFilePath);
            $newCopiedProduct->setAttribute('image', $newFileName); // Ensure attribute is marked as changed
            $newCopiedProduct->save();
        }
    }
}
