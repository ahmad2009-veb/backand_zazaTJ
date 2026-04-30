<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

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
            'user_number' => $this->user_number,
            'f_name' => $this['f_name'],
            'l_name' => $this['l_name'],
            'phone' => $this['phone'],
            'birth_date' =>$this['birth_date'],
            'loyalty_point' => $this['loyalty_points'] ?? 0,
            'loyalty_point_percentage' => $this['loyalty_points_percentage'] ?? 0,
            'avatar' =>$this['image'] ? url('storage/profile/' . $this['image']) : null,
            'address' => $this['addresses']->first() ? [
                'id' => $this['addresses']->first()->id,
                'road' => $this['addresses']->first()->road,
                'house' => $this['addresses']->first()->house,
                'apartment' => $this['addresses']->first()->apartment,
                'domofon_code' => $this['addresses']->first()->domofon_code
            ] : null
        ];
    }
}
