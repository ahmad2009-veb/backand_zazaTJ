<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{


    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::enum(TransactionTypeEnum::class)],
            'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'date_from'  => ['nullable', 'date',  'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date',  'after_or_equal:date_from']
        ]);

        $transactions = auth()->user()->transactions()
            ->filter($request)
            ->paginate($request->input('paginate', 10));
        return TransactionResource::collection($transactions);
    }

    public function typesOptions()
    {
        return response()->json(['data' => TransactionTypeEnum::options()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'amount' => 'required|integer',
            'transaction_category_id' => 'required|integer|exists:transaction_categories,id',
            'description' => 'nullable|string',
            'type' => ['required', 'string', Rule::enum(TransactionTypeEnum::class)]
        ]);

        Transaction::query()->create([
            'name' => $request->name,
            'amount' => $request->amount,
            'transaction_category_id' => $request->transaction_category_id,
            'description' => $request->description,
            'type' => $request->type,
            'admin_id' => auth()->user()->id,
            'status' => TransactionStatusEnum::SUCCESS
        ]);

        return response()->json(['data' => 'Успешно сохранено']);
    }

    public function show(Transaction $transaction)
    {
        return TransactionResource::make($transaction);
    }

    public function update(Request $request,  Transaction $transaction)
    {
        $request->validate([
            'name' => 'required|string',
            'amount' => 'required|integer',
            'transaction_category_id' => 'required|integer|exists:transaction_categories,id',
            'description' => 'nullable|string',
            'type' => ['required', 'string', Rule::enum(TransactionTypeEnum::class)]
        ]);

        $transaction->update([
            'name' => $request->name,
            'amount' => $request->amount,
            'transaction_category_id' => $request->transaction_category_id,
            'description' => $request->description,
            'type' => $request->type
        ]);

        return response()->json(['message' => 'ok']);
    }
}
