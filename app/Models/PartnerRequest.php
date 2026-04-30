<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerRequest extends Model
{
    protected $table = 'partner_requests';

    protected $fillable = [
        'type',
        'merchant_name',
        'contact_name',
        'phone',
        'email',
        'city',
        'address',
        'company_name',
        'description',
        'status',
        'admin_notes',
    ];
}
