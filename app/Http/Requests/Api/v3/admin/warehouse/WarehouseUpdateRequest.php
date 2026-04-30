<?php

namespace App\Http\Requests\Api\v3\admin\warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseUpdateRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');
        return [
            'name' => ['required', 'string', 'max:255', 'unique:warehouses,name,' . $warehouse->id],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'responsible' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'string', 'max:255'],
        ];
    }
}
