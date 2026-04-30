<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'store_id'];
    protected $casts = [
        'store_id' => 'integer',
    ];
    public function store() {
        return $this->belongsTo(Store::class);
    }
}
