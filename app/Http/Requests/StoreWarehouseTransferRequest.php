<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreWarehouseTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $transferType = $this->input('transfer_type');

        $rules = [
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'name' => 'nullable|string|max:255',
            'transfer_type' => 'required|in:internal,external',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            // For products with variations
            'products.*.variations' => 'nullable|array',
            'products.*.variations.*.variation_id' => 'nullable|string',
            'products.*.variations.*.quantity' => 'required|numeric|min:0',
            'products.*.variations.*.cost_price' => 'nullable|numeric|min:0',
            'products.*.variations.*.sale_price' => 'nullable|numeric|min:0',
            'products.*.variations.*.notes' => 'nullable|string',
            // For legacy products without variations
            'products.*.quantity' => 'nullable|numeric|min:0',
            'products.*.cost_price' => 'nullable|numeric|min:0',
            'products.*.sale_price' => 'nullable|numeric|min:0',
        ];

        if ($transferType === 'internal') {
            // Internal: to_warehouse_id required
            $rules['to_warehouse_id'] = 'required|exists:warehouses,id|different:from_warehouse_id';
        } else {
            // External: to_vendor_id required, to_warehouse_id optional
            $rules['to_vendor_id'] = 'required|exists:vendors,id';
            $rules['to_warehouse_id'] = 'nullable|exists:warehouses,id';
            // Wallets are optional for external transfers
            $rules['wallets'] = 'nullable|array';
            $rules['wallets.*.id'] = 'required_with:wallets|exists:wallets,id';
            $rules['wallets.*.amount'] = 'required_with:wallets|numeric|min:0.01';
            $rules['wallets.*.vendor_wallet_id'] = 'nullable|exists:vendor_wallets,id';
            $rules['wallets.*.wallet_id'] = 'nullable|exists:wallets,id';
            // Installment fields
            $rules['is_installment'] = 'nullable|boolean';
            $rules['initial_payment'] = 'nullable|numeric|min:0';
            $rules['total_due'] = 'required_if:is_installment,true|numeric|min:0';
            $rules['remaining_balance'] = 'required_if:is_installment,true|numeric|min:0';
            $rules['due_date'] = 'nullable|date';
        }

        return $rules;
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'from_warehouse_id.required' => 'Исходный склад обязателен',
            'from_warehouse_id.exists' => 'Исходный склад не найден',
            'to_warehouse_id.required' => 'Целевой склад обязателен',
            'to_warehouse_id.exists' => 'Целевой склад не найден',
            'to_warehouse_id.different' => 'Исходный и целевой склады должны быть разными',
            'to_vendor_id.required' => 'Целевой вендор обязателен для внешнего перемещения',
            'to_vendor_id.exists' => 'Вендор не найден',
            'transfer_type.required' => 'Тип перемещения обязателен',
            'transfer_type.in' => 'Тип перемещения должен быть внутренним или внешним',
            'products.required' => 'Товары обязательны',
            'products.*.product_id.required' => 'ID товара обязателен',
            'products.*.product_id.exists' => 'Товар не найден',
            'products.*.quantity.required' => 'Количество обязательно',
            'products.*.quantity.min' => 'Количество должно быть больше 0',
            'wallets.*.id.required_with' => 'ID кошелька обязателен',
            'wallets.*.id.exists' => 'Кошелек не найден',
            'wallets.*.amount.required_with' => 'Сумма кошелька обязательна',
            'wallets.*.amount.min' => 'Сумма кошелька должна быть больше 0',
            'is_installment.boolean' => 'Поле рассрочки должно быть логическим',
            'initial_payment.numeric' => 'Первоначальный взнос должен быть числом',
            'initial_payment.min' => 'Первоначальный взнос должен быть больше или равен 0',
            'total_due.required_if' => 'Общая сумма к оплате обязательна при рассрочке',
            'total_due.numeric' => 'Общая сумма к оплате должна быть числом',
            'total_due.min' => 'Общая сумма к оплате должна быть больше или равна 0',
            'remaining_balance.required_if' => 'Остаток баланса обязателен при рассрочке',
            'remaining_balance.numeric' => 'Остаток баланса должен быть числом',
            'remaining_balance.min' => 'Остаток баланса должен быть больше или равен 0',
            'due_date.date' => 'Дата оплаты должна быть валидной датой',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $isInstallment = $this->input('is_installment', false);
            $transferType = $this->input('transfer_type');
            $fromWarehouseId = $this->input('from_warehouse_id');
            $products = $this->input('products', []);

            // Validate product availability in warehouse
            foreach ($products as $index => $productData) {
                $productId = $productData['product_id'] ?? null;
                if (!$productId) {
                    continue;
                }

                $product = Product::find($productId);
                if (!$product) {
                    $validator->errors()->add(
                        "products.{$index}.product_id",
                        "Товар с ID {$productId} не найден"
                    );
                    continue;
                }

                // Check if product has variations array or is legacy format
                if (!empty($productData['variations']) && is_array($productData['variations'])) {
                    // Validate each variation
                    foreach ($productData['variations'] as $varIndex => $variationData) {
                        $requestedQuantity = (float)($variationData['quantity'] ?? 0);
                        if ($requestedQuantity <= 0) {
                            continue;
                        }

                        $variationId = $variationData['variation_id'] ?? null;
                        
                        // Find the variation
                        $variation = null;
                        if ($variationId) {
                            $variation = ProductVariation::where('product_id', $productId)
                                ->where('variation_id', $variationId)
                                ->first();
                        }

                        if (!$variation) {
                            $validator->errors()->add(
                                "products.{$index}.variations.{$varIndex}.variation_id",
                                "Вариация '{$variationId}' для товара '{$product->name}' не найдена"
                            );
                            continue;
                        }

                        // Get available quantity for this variation
                        $availableQuantity = (float)$variation->quantity;

                        if ($requestedQuantity > $availableQuantity) {
                            $difference = $requestedQuantity - $availableQuantity;
                            $validator->errors()->add(
                                "products.{$index}.variations.{$varIndex}.quantity",
                                "Недостаточно товара '{$product->name}' (вариация: {$variation->variation_id}) на складе. Доступно: {$availableQuantity}, требуется: {$requestedQuantity}, не хватает: {$difference}"
                            );
                        }
                    }
                } else {
                    // Legacy format without variations - validate product quantity
                    $requestedQuantity = (float)($productData['quantity'] ?? 0);
                    if ($requestedQuantity <= 0) {
                        continue;
                    }

                    // Check if product has variations - if yes, sum them; otherwise use product quantity
                    $variations = $product->variations()->get();
                    $availableQuantity = 0;

                    if ($variations->count() > 0) {
                        // Product has variations - sum their quantities
                        $availableQuantity = (float)$variations->sum('quantity');
                    } else {
                        // No variations - check direct warehouse_id or warehouse_product table
                        if ($product->warehouse_id == $fromWarehouseId) {
                            $availableQuantity = (float)($product->quantity ?? 0);
                        } else {
                            // Check warehouse_product pivot table
                            $warehouseProduct = WarehouseProduct::where('product_id', $productId)
                                ->where('warehouse_id', $fromWarehouseId)
                                ->first();
                            $availableQuantity = $warehouseProduct ? (float)($warehouseProduct->quantity ?? 0) : 0;
                        }
                    }

                    if ($requestedQuantity > $availableQuantity) {
                        $difference = $requestedQuantity - $availableQuantity;
                        $validator->errors()->add(
                            "products.{$index}.quantity",
                            "Недостаточно товара '{$product->name}' на складе. Доступно: {$availableQuantity}, требуется: {$requestedQuantity}, не хватает: {$difference}"
                        );
                    }

                    // Also check if product exists in warehouse
                    if ($availableQuantity == 0) {
                        if ($product->warehouse_id != $fromWarehouseId) {
                            $warehouseProduct = WarehouseProduct::where('product_id', $productId)
                                ->where('warehouse_id', $fromWarehouseId)
                                ->exists();
                            if (!$warehouseProduct) {
                                $validator->errors()->add(
                                    "products.{$index}.product_id",
                                    "Товар '{$product->name}' не найден на исходном складе"
                                );
                            }
                        }
                    }
                }
            }

            // Only validate installments for external transfers
            if ($transferType === 'external' && $isInstallment) {
                $initialPayment = (float)($this->input('initial_payment', 0));
                $totalDue = (float)($this->input('total_due', 0));
                $remainingBalance = (float)($this->input('remaining_balance', 0));

                // Validate that initial_payment + remaining_balance equals total_due
                $sum = round($initialPayment + $remainingBalance, 2);
                $totalDueRounded = round($totalDue, 2);
                
                if (abs($sum - $totalDueRounded) > 0.01) {
                    $validator->errors()->add(
                        'installment_amounts',
                        "Сумма первоначального взноса и остатка баланса должна равняться общей сумме к оплате. Первоначальный взнос: {$initialPayment}, Остаток: {$remainingBalance}, Сумма: {$sum}, К оплате: {$totalDueRounded}"
                    );
                }

                // Validate that initial_payment is not greater than total_due
                if ($initialPayment > $totalDue) {
                    $validator->errors()->add(
                        'initial_payment',
                        'Первоначальный взнос не может быть больше общей суммы к оплате'
                    );
                }

                // Validate that remaining_balance is not greater than total_due
                if ($remainingBalance > $totalDue) {
                    $validator->errors()->add(
                        'remaining_balance',
                        'Остаток баланса не может быть больше общей суммы к оплате'
                    );
                }
            }
        });
    }
}

