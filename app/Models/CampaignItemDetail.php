<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignItemDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'campaign_items_details';
    protected $casts = [
        'add_ons' => 'array'
    ];

    public function food()
    {
        return $this->hasOne(Food::class, 'id', 'food_id');
    }


    public function get_addOns($array)
    {
        if ($array == null) return null;
        $arr = json_decode($array, true);
        $data = array_map('intval', $arr);
        return AddOn::whereIn('id', $data)->get();

    }


}
