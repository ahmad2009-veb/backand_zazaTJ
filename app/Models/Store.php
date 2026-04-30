<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\CentralLogics\RestaurantLogic;
use App\CentralLogics\StoreLogic;
use App\Traits\HasVendorNumbering;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory, HasVendorNumbering;

    protected $dates = ['opening_time', 'closing_time'];
    protected $guarded = ['id'];
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

    protected $appends = ['available_time_starts',
        'available_time_ends',
        'avg_rating',
        'rating_count',
        'discount_in_percent'

    ];

    public function mainStore()
    {
        return $this->belongsTo(Store::class, 'main_store_id');
    }

    public function subStores()
    {
        return $this->hasMany(Store::class, 'main_store_id');
    }
    public function getAvailableTimeStartsAttribute()
    {
        return $this->opening_time ? $this->opening_time->format('H:i') : null;
    }

    public function getAvailableTimeEndsAttribute()
    {
        return $this->closing_time ? $this->closing_time->format('H:i') : null;
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

    public function getAvgRatingAttribute()
    {
        $ratings = StoreLogic::calculate_store_rating($this->rating);
        return round($ratings['rating'], 1);
    }

    public function getRatingCountAttribute()
    {
        $ratings = StoreLogic::calculate_store_rating($this->rating);
        return $ratings['total'];
    }

    public function getDiscountInPercentAttribute()
    {
        return Helpers::get_store_discount($this) && $this->discount->discount_type == 'percent' ? $this->discount->discount : 0;
    }

//
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeOpened($query)
    {
        return $query->where('active', 1);
    }

    public function warehouses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_store', 'store_id', 'warehouse_id');
    }

    public function employeeRoles()
    {
        return $this->hasMany(EmployeeRole::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'store_products', 'store_id', 'product_id')
            ->withTimestamps(); // If you have timestamps in your pivot table
    }

    /**
     * Get the number field name for vendor numbering
     */
    public function getNumberField(): string
    {
        return 'store_number';
    }

    /**
     * Get the display prefix for formatted ID
     */
    public function getDisplayPrefix(): string
    {
        return 'STORE';
    }
}
