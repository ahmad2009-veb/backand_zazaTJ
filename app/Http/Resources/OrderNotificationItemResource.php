<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderNotificationItemResource extends JsonResource
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
            'id' => $this->id,
            'title' => $this->data['title'],
            'order_id' => $this->data['order_id'],
            'description' => trans("messages.{$this['order_status']}"),
            'order_status' => $this->order_status,
            'created_at' => $this->created_at
        ];
    }
}

