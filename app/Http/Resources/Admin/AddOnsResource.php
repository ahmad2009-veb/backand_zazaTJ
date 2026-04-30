<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AddOnsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'status' => $this['status'],
            'price' => $this['price'],
            'restaurant' => $this['restaurant'] ? [
                'id' => $this['restaurant']['id'],
                'name' => $this['restaurant']['name'],
                'zone' => [
                    'id' => $this['restaurant']['zone']['id'],
                    'name' => $this['restaurant']['zone']['name'],
                ]
            ] : null
        ];
    }
}
