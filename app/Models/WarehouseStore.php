<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseStore extends Model
{
    use HasFactory;

    protected $table = 'warehouse_store';
    protected $guarded = ['id'];

}
