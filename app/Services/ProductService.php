<?php

namespace App\Services;

use Exception;
use Rap2hpoutre\FastExcel\FastExcel;

class ProductService
{
    public   $rules = [
        '*.name' => "required|string|max:255",
        '*.warehouse_id' => "required|integer|exists:warehouses,id",
        '*.category_id' => "required|integer|min:1",
        '*.price' => "required|numeric|min:0",
        '*.purchase_price' => "required|numeric|min:0",
        '*.description' => "nullable|string|max:1000",
    ];

    public $warehouseProductRules = [
        '*.product_id' => 'required|int|exists:products,id',
        '*.quantity' => 'required|int',
        '*.purchase_price' => 'required|int',
        '*.product_code' => 'nullable|string',
        '*.retail_price' => 'required|int',
        '*.image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024'
    ];

    public function removeBrackets($collection)
    {
        try {
            $processedData = $collection->map(function ($item) {
                return collect($item)->mapWithKeys(function ($value, $key) {
                    $cleanKey = preg_replace('/\s?\(.*?\)/', '', $key);
                    return [$cleanKey => $value];
                });
            });

            return $processedData;
        } catch (\Throwable $th) {
            throw new Exception('Error on removing brackets:' . $th->getMessage(), $th->getCode());
        }
    }

    public function exportProductProfitability($request, $user)
    {
        $query = $user->products();

        if ($request->type === 'date') {
            $date_from = $request->date_from;
            $date_to = $request->date_to;
            if (!$date_from || !$date_to) {
                return response()->json(['error' => 'Invalid date range'], 400);
            }
            $query->whereBetween('products.created_at', [$date_from, $date_to]);
        }

        if ($request->type === 'id') {
            $id_from = $request->id_from;
            $id_to = $request->id_to;

            if (!$id_from || !$id_to) {
                return response()->json(['error' => 'Invalid ID range'], 400);
            }
            $query->whereBetween('products.id', [$id_from, $id_to]);
        }


        $products = $query->cursor();

        return $products->map(function ($product) {
            return     [
                'Наименование товара' => $product['name'],
                'Цена продажи' => $product['price'],
                'Себестоимость товара' => $product['purchase_price'],
                'Прибыль на единицу' => $product['profit_per_unit'],
                'Объем продаж' => $product['sales_count'],
                'Общая прибыльность' => $product['total_profit'],
                'Прибыльность товара' => $product['profitability']
            ];
        });
    }
}
