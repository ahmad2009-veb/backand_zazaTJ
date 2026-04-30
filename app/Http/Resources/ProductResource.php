<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{


    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'price' => $this['price'],
            'image' => url('storage/product/' . $this->image),
            'has_variations' => !empty(json_decode($this['variations'])),
            'has_add_ons' => !empty(json_decode($this['add_ons'])),
        ];
    }

}

