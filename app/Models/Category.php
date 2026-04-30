<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $casts = [
        'parent_id' => 'integer',
        'position'  => 'integer',
        'priority'  => 'integer',
        'status'    => 'boolean',
    ];
    protected $hidden = [
        'laravel_through_key'
     ];

    protected $guarded = ['id'];
    protected static function booted()
    {
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
        return $query->where('categories.status', 1);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function childes()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function foods() {
        return $this->hasMany(Food::class, 'category_id', 'id');
    }

    public function scopeRestaurants()
    {

        $restaurant_ids = Food::where('category_id', $this->id)->pluck('restaurant_id')->unique();
        return  Restaurant::whereIn('id', $restaurant_ids)->get();

    }

    public function products() {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    public function store() {
        return $this->belongsTo(Store::class);
    }


}
