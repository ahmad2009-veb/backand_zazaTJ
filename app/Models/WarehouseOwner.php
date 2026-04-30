<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseOwner extends Model
{
    use HasFactory;

    protected $table = 'warehouse_owners';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token', 'firebase_token', 'email_verified_at'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function warehouse() {
        return $this->belongsTo(Warehouse::class);
    }

}
