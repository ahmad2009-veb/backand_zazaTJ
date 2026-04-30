<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $deliveryAddress = json_decode($this->delivery_address, true);
        return [
            'id' => $this->id,
            'order_status' => $this->order_status,
            'customer' => [
                'id' => $this->customer ? $this->customer->id : 0,
                'f_name' => $this->customer ? $this->customer->f_name : $deliveryAddress['contact_person_name'],
                'l_name' => $this->customer ? $this->customer->l_name : null,
                'phone' => $this->customer ? $this->customer->phone : $deliveryAddress['contact_person_number'],
                'avatar' => $this->customer
                    ? $this->customer->image !== null
                        ? url('storage/profile/' . $this->customer->image)
                        : null
                    : null,
            ],
            'delivery_man' => $this->delivery_man ? [
                'id' => $this->delivery_man->id,
                'f_name' => $this->delivery_man->f_name,
                'l_name' => $this->delivery_man->l_name,
                'phone' => $this->delivery_man->phone,
                'avatar' => $this->delivery_man->image ? url('storage/delivery-man/' . $this->delivery_man->image) :null
            ] : null,
            'delivery_address' => json_decode($this->delivery_address, true),
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'phone' => $this->store->phone,
                'address' => $this->store->address,
                'logo' => $this->store->logo !== null ? url('storage/restaurant/' . $this->restaurant->logo) : null,
            ] : null,
            'warehouse' => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name
            ] : [
                'id' => 9999,
                'name' => 'Центральный склад'
            ],
//            'main_restaurant' => MainRestaurantResource::make($this->restaurant->mainRestaurant),
            'zone_id' => $this->store?->zone_id,
            'products' => OrderShowProductResource::collection($this->details),
            'order_amount' => $this->order_amount,
            'coupon_discount_amount' => $this->coupon_discount_amount,
            'delivery_charge' => $this->delivery_charge,
            'created_at' => $this->created_at,
            'comment' => $this->comment,
            'comment_for_store' => $this->comment_for_store,
        ];
    }
}
