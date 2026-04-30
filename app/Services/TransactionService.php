<?php

namespace App\Services;

use App\Enums\TransactionTypeEnum;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    public function saleTransactionStore($order_amount, $user, $guard,$sale)
    {
      $transaction =   Transaction::query()->create([
            'name' => 'Реализация',
            'amount' => $order_amount,
            'vendor_id' => $guard === 'vendor' ? $user->id : null,
            'admin_id' => $guard === 'admin' ? $user->id : null,
            'transaction_category_id' => $this->getTransactionCategoryId($user, $guard),
            'description' => 'Реализация',
            'type' => TransactionTypeEnum::INCOME,
            'status'  => 'success',
            'sale_id' => $sale->id,

        ]);

        return $transaction;
    }

    private function getTransactionCategoryId($user, $guard)
    {

        $category  = TransactionCategory::query()->where(['name' => 'Реализация', "{$guard}_id" => $user->id])->first();
        return $category->id;
    }
}
