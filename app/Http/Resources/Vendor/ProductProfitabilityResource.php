<?php

namespace App\Http\Resources\Vendor;

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
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'purchase_price' => $this->purchase_price,
            'profit_per_unit' => $this->profit_per_unit,
            'sales_count' => $this->sales_count,
            'total_profit' => $this->total_profit,
            'profitability' => $this->profitability,
            'cost-effectiveness' => $this->cost_effectiveness
        ];
    }
}
