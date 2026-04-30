<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPointTransaction extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'credit',
        'debit',
        'balance',
        'reference',
        'transaction_type',
    ];

    protected $casts = [
        'user_id'   => 'integer',
        'credit'    => 'float',
        'debit'     => 'float',
        'balance'   => 'float',
        'reference' => 'string',
    ];

    /**
     * Get the user that owns the LoyaltyPointTransaction
     *
     * @return \App\Models\User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
