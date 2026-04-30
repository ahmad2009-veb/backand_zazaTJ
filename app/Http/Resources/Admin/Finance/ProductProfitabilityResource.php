<?php

namespace App\Http\Resources\Admin\Finance;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductProfitabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'purchase_price' => $this->purchase_price,
            'profit_per_unit' => $this->profit_per_unit,
            'sales_count' => $this->sales_count,
            'total_profit' => $this->total_profit,
            'profitability' => $this->profitability,
            'warehouse' => $this->warehouse?->name,
        ];
    }
}
