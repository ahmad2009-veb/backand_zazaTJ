<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function campaign() {
        return $this->hasOne(Campaign::class, 'id', 'campaign_id');
    }



}
