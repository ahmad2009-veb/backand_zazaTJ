<?php

namespace App\Http\Requests\Api\v3\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TransactionTypeEnum;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
            'amount' => 'required|numeric',
            'transaction_category_id' => 'required|integer|exists:transaction_categories,id',
            'description' => 'nullable|string',
            'type' => ['required', 'string', Rule::enum(TransactionTypeEnum::class)],
            'wallet_id' => 'nullable|integer|exists:wallets,id'
        ];
    }
}
