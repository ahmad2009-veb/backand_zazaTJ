<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class
CategoryResource extends JsonResource
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
            'priority' => $this['priority'],
            'image' => $this['image'] ? url('storage/category/' . $this['image']) : null,
            'sub_categories' => $this->childes->map(function ($el) {
                return [
                    'id' => $el['id'],
                    'name' => $el['name'],
                    'priority' => $el['priority'],
                    'image' => $el['image'] ? url('storage/category/' . $this['image']) : null,
                    'status' => $el['status']
                ];
            })
        ];
    }
}


