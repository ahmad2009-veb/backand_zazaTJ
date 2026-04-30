<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Traits\HasVendorNumbering;

class VendorEmployee extends Authenticatable
{
    use Notifiable, HasApiTokens, HasVendorNumbering;

    public $fillable = [
        'f_name',
        'l_name',
        'email',
        'phone',
        'password',
        'image',
        'modules',
        'vendor_id',
        'employee_number',
    ];

    protected $hidden = [
        'password',
        'auth_token',
        'remember_token',
    ];

    protected $casts = [
        'modules' => 'array',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the number field name for vendor numbering
     */
    public function getNumberField(): string
    {
        return 'employee_number';
    }

    /**
     * Get the display prefix for formatted ID
     */
    public function getDisplayPrefix(): string
    {
        return 'EMP';
    }

    /**
     * Get employee modules/permissions
     */
    public function getModules(): array
    {
        return $this->modules ?? [];
    }

    /**
     * Check if employee has access to a specific module
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, $this->getModules());
    }
}
