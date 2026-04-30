<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderReceiverResource extends JsonResource
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
            'order_status' => $this->order_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'admin' => $this->admin ? [
                'id' => $this->admin->id,
                'f_name' => $this->admin->f_name,
                'l_name' => $this->admin->l_name,
                'image' => $this->admin->image ? url('storage/admin/' . $this->admin->image) : null,
                'phone' => $this->admin->phone
            ] : null,
            'role' => $this->admin?->role ? [
                'name' => $this->admin?->role->name
            ] : null,
            'vendor' => $this->vendor ? [
                'id' => $this->vendor->id,
                'f_name' => $this->vendor->f_name,
                'l_name' => $this->vendor->l_name,
                'image' => $this->vendor->image ? url('storage/vendor' . $this->vendor->image) : null,
                'phone' => $this->vendor->phone
            ] : null
        ];
    }
}
