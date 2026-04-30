<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Scopes\RestaurantScope;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'status' => 'boolean',
        'discount' => 'float',
        'avg_rating' => 'float',
        'set_menu' => 'integer',
        'category_id' => 'integer',
        'restaurant_id' => 'integer',
        'reviews_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'veg' => 'integer',
    ];

    protected static function booted()
    {
        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            static::addGlobalScope(new RestaurantScope);
        }

        static::addGlobalScope(new ZoneScope);

        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with([
                'translations' => function ($query) {
                    return $query->where('locale', app()->getLocale());
                },
            ]);
        });
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->whereHas('restaurant', function ($query) {
            return $query->where('status', 1);
        });
    }

    public function scopeAvailable($query, $time)
    {
        $query->where(function ($q) use ($time) {
            $q->where('available_time_starts', '<=', $time)->where('available_time_ends', '>=', $time);
        });
    }

    public function scopePopular($query)
    {
        return $query->orderBy('order_count', 'desc');
    }

    // public function rating()
    // {
    //     return $this->hasMany(Review::class)
    //         ->select(DB::raw('avg(rating) average, count(food_id) rating_count, food_id'))
    //         ->groupBy('food_id');
    // }

    public function reviews()
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function getCategoryAttribute()
    {
        $category = Category::find(json_decode($this->category_ids)[0]->id);

        return $category ? $category->name : trans('messages.uncategorize');
    }


    public function scopeType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('veg', true);
        } else {
            if ($type == 'non_veg') {
                return $query->where('veg', false);
            }
        }

        return $query;
    }

    public function getAddOns(): array
    {
        $addOns = Helpers::addon_data_formatting(
            data: AddOn::withoutGlobalScope('translate')
                ->whereIn('id', json_decode($this->add_ons))
                ->active()
                ->get(),
            multi_data: true,
        );

        return array_map(fn(AddOn $addOn) => [
            'id'    => $addOn->id,
            'name'  => $addOn->name,
            'price' => $addOn->price,
        ], $addOns);
    }
}
