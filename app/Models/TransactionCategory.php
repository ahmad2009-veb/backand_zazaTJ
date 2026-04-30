<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    use HasFactory;
    protected  $fillable = [
        'name',
        'parent_id',
        'vendor_id',
        'admin_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function subcategories()
    {
        return $this->hasMany(TransactionCategory::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(TransactionCategory::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(TransactionCategory::class, 'parent_id');
    }
}
