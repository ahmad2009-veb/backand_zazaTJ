<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Campaign extends Model
{
    use HasFactory;

    protected $dates = ['created_at', 'updated_at', 'start_date', 'end_date'];

    // protected $casts = ['start_time'=>'datetime', 'end_time'=>'datetime'];
    protected $casts = [
        'status' => 'integer',
        'admin_id' => 'integer',

    ];

    protected static function booted()
    {
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with([
                'translations' => function ($query) {
                    return $query->where('locale', app()->getLocale());
                },
            ]);
        });

        static::retrieved(function ($restaurant) {
            $restaurant->rule();
        });
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function getStartTimeAttribute($value)
    {
        return $value ? date('H:i', strtotime($value)) : $value;
    }

    public function getEndTimeAttribute($value)
    {
        return $value ? date('H:i', strtotime($value)) : $value;
    }

    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeRunning($query)
    {
        return $query->where(function ($q) {
            $q->whereDate('end_date', '>=', date('Y-m-d'))->orWhereNull('end_date');
        })->where(function ($q) {
            $q->whereDate('start_date', '<=', date('Y-m-d'))->orWhereNull('start_date');
        })->where(function ($q) {
            $q->whereTime('start_time', '<=', date('H:i:s'))->orWhereNull('start_time');
        })->where(function ($q) {
            $q->whereTime('end_time', '>=', date('H:i:s'))->orWhereNull('end_time');
        });
    }

    public function items()
    {
        return $this->hasManyThrough(
            CampaignItemDetail::class,
            CampaignItem::class,
            'campaign_id',
            'campaign_items_id', 'id', 'id');
    }

    public function rule()
    {
        return $this->hasOne(CampaignRule::class,'id', 'campaign_rule_id');
    }

    public function scopePastCampaigns($query) {
        return $query->where('end_date', '<', now()->startOfDay());
    }

    public function userCampaigns()
    {
        return $this->hasMany(UserCampaign::class, 'campaign_id', 'id');
    }

    public function campaignItems() {
        return $this->hasMany(CampaignItem::class, 'campaign_id', 'id');
    }

}
