<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class VendorUserResource extends JsonResource
{
    // public static $wrap = null;
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
            'user_number' => $this->user_number,
            'f_name' => $this->f_name,
            // 'l_name' => $this->l_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'image' => $this->image,
            'store' => StoreResource::make($this->store),
            'created_at' => $this->created_at,
        ];
    }
}
