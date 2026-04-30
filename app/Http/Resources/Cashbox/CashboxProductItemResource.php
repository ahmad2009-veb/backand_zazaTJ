<?php

namespace App\Http\Resources\Cashbox;

use Illuminate\Http\Resources\Json\JsonResource;

class CashboxProductItemResource extends JsonResource
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
            'id' => $this['id'],
            'name' => $this['name'],
            'price' =>$this['price'],
            'status' => $this['status'],
            'image' =>$this['image'] ? url('storage/product/' . $this['image']) : null,
            'variations' => json_decode($this['variations']),
            'choice_options' => json_decode($this['choice_options']),
            'add_ons' => $this->getAddons(),

        ];
    }
}

