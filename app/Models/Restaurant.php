<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\CentralLogics\RestaurantLogic;
use App\Scopes\ZoneScope;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Restaurant extends Model
{
    use Sluggable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'logo',
        'latitude',
        'longitude',
        'address',
        'minimum_order',
        'comission',
        'tax',
        'delivery_charge',
        'delivery_time',
        'pickup_time',
        'password',
        'status',
        'vendor_id',
        'zone_id',
        'delivery',
        'take_away',
        'schedule_order',
        'opening_time',
        'closeing_time',
        'off_day',
        'gst_status',
        'gst_code',
        'self_delivery_system',
        'pos_system',
        'minimum_shipping_charge',
        'per_km_shipping_charge',
        'restaurant_model',
        'maximum_shipping_charge',
        'slug',
        'order_subscription_active',
        'cutlery',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'announcement',
        'announcement_message',
        'qr_code',
        'additional_data',
        'additional_documents',
        'restaurant_sub_update_time',
        'food_section',
        'reviews_section',
        'active',
        'free_delivery',
        'cover_photo',
        'halal_tag_status',
        'extra_packaging_status',
        'extra_packaging_amount',
        'veg_status',
        'non_veg_status',
        'order_place_to_schedule_interval',
        'featured',
        'prescription_order',
        'webz',
        'instant',
        'characteristic',
    ];

    protected $dates = ['opening_time', 'closeing_time'];

    protected $casts = [
        'minimum_order' => 'float',
        'comission' => 'float',
        'tax' => 'float',
        'delivery_charge' => 'float',
        'schedule_order' => 'boolean',
        'free_delivery' => 'boolean',
        'vendor_id' => 'integer',
        'status' => 'boolean',
        'delivery' => 'boolean',
        'take_away' => 'boolean',
        'zone_id' => 'integer',
        'food_section' => 'boolean',
        'reviews_section' => 'boolean',
        'active' => 'boolean',
        'gst_status' => 'boolean',
        'pos_system' => 'boolean',
        'self_delivery_system' => 'integer',
        'open' => 'integer',
        'gst_code' => 'string',
        'off_day' => 'string',
        'gst' => 'string',
        'veg' => 'integer',
        'non_veg' => 'integer',
        'minimum_shipping_charge' => 'float',
        'per_km_shipping_charge' => 'float',
    ];

    protected $appends = ['gst_status', 'gst_code', 'available_time_starts',
        'available_time_ends',
        'avg_rating',
        'rating_count',
        'discount_in_percent'

    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'gst',
    ];


    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);

//        static::saving(function ($restaurant) {
//            $restaurant->slug = Str::slug($restaurant->name);
//        });
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function mainRestaurant()
    {
        return $this->belongsTo(Restaurant::class, 'main_restaurant_id');
    }

    public function subRestaurants()
    {
        return $this->hasMany(Restaurant::class, 'main_restaurant_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function employeeRoles()
    {
        return $this->hasMany(EmployeeRole::class);
    }

    public function foods()
    {
        return $this->hasMany(Food::class);
    }

    public function schedules()
    {
        return $this->hasMany(RestaurantSchedule::class)->orderBy('opening_time');
    }

    public function deliverymen()
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function discount()
    {
        return $this->hasOne(Discount::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function scopeCategories()
    {
        return $this->foods->load('subCategory')
            ->map(fn(Food $food) => $food->subCategory)
            ->unique('id')
            ->values();
    }

    public function itemCampaigns()
    {
        return $this->hasMany(ItemCampaign::class);
    }

    public function reviews()
    {
        return $this->hasManyThrough(Review::class, Food::class);
    }

    public function getScheduleOrderAttribute($value)
    {
        return (boolean)(\App\CentralLogics\Helpers::schedule_order() ? $value : 0);
    }

    public function getRatingAttribute($value): array
    {
        $ratings = json_decode($value, true);


        $rating5 = $ratings ? $ratings[5] : 0;
        $rating4 = $ratings ? $ratings[4] : 0;
        $rating3 = $ratings ? $ratings[3] : 0;
        $rating2 = $ratings ? $ratings[2] : 0;
        $rating1 = $ratings ? $ratings[1] : 0;

        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }

    public function getGstStatusAttribute()
    {
        return (boolean)($this->gst ? json_decode($this->gst, true)['status'] : 0);
    }

    public function getGstCodeAttribute()
    {
        return (string)($this->gst ? json_decode($this->gst, true)['code'] : '');
    }

    public function scopeDelivery($query)
    {
        $query->where('delivery', 1);
    }

    public function scopeTakeaway($query)
    {
        $query->where('take_away', 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeOpened($query)
    {
        return $query->where('active', 1);
    }

    public function scopeWithOpen($query)
    {
        $query->selectRaw('*, IF(((select count(*) from `restaurant_schedule` where `restaurants`.`id` = `restaurant_schedule`.`restaurant_id` and `restaurant_schedule`.`day` = ' . now()->dayOfWeek . ' and `restaurant_schedule`.`opening_time` < "' . now()->format('H:i:s') . '" and `restaurant_schedule`.`closing_time` >"' . now()->format('H:i:s') . '") > 0), true, false) as open');
    }

    public function scopeWeekday($query)
    {
        return $query->where('off_day', 'not like', "%" . now()->dayOfWeek . "%");
    }

    public function scopeType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('veg', true);
        } else {
            if ($type == 'non_veg') {
                return $query->where('non_veg', true);
            }
        }

        return $query;

    }

    public function restaurantGroup()
    {
        return $this->belongsTo(RestaurantGroup::class);
    }


    public function getAvailableTimeStartsAttribute()
    {
        return $this->opening_time ? $this->opening_time->format('H:i') : null;
    }

    // Accessor for available_time_ends
    public function getAvailableTimeEndsAttribute()
    {
        return $this->closeing_time ? $this->closeing_time->format('H:i') : null;
    }

    // Accessor for avg_rating
    public function getAvgRatingAttribute()
    {
        $ratings = RestaurantLogic::calculate_restaurant_rating($this->rating);
        return round($ratings['rating'], 1);
    }

    // Accessor for rating_count
    public function getRatingCountAttribute()
    {
        $ratings = RestaurantLogic::calculate_restaurant_rating($this->rating);
        return $ratings['total'];
    }

    // Accessor for discount_in_percent
    // Commented it out, coz the logic of get_restaurant_discount within Helper is not realized yet!
    // public function getDiscountInPercentAttribute()
    // {
    //     return Helpers::get_restaurant_discount($this) && $this->discount->discount_type == 'percent' ? $this->discount->discount : 0;
    // }

}
