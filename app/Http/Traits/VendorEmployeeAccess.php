<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait VendorEmployeeAccess
{
    /**
     * Get the acting vendor - works for both vendor and employee authentication
     * 
     * @return \App\Models\Vendor|null
     */
    protected function getActingVendor()
    {
        // TOKEN NAME-BASED SOLUTION: Check token names to determine user type
        $vendorUser = Auth::guard('vendor_api')->user();
        $employeeUser = Auth::guard('vendor_employee_api')->user();

        // Get token name to determine the actual user type
        $tokenName = null;
        if ($vendorUser && method_exists($vendorUser, 'token')) {
            $token = $vendorUser->token();
            $tokenName = $token ? $token->name : null;
        } elseif ($employeeUser && method_exists($employeeUser, 'token')) {
            $token = $employeeUser->token();
            $tokenName = $token ? $token->name : null;
        }

        // PRIORITY: Use token name to determine user type
        if ($tokenName && str_contains($tokenName, 'VendorAuth:') && $vendorUser) {
            return $vendorUser;
        }

        if ($tokenName && str_contains($tokenName, 'EmployeeAuth:') && $employeeUser) {
            return $employeeUser->vendor;
        }

        // FALLBACK: For tokens without specific names (old tokens), use the old logic
        if ($vendorUser && $employeeUser) {
            // Both exist - this is the ID collision case
            // For backward compatibility, prioritize employee for now
            return $employeeUser->vendor;
        }

        if ($employeeUser && $employeeUser instanceof \App\Models\VendorEmployee) {
            return $employeeUser->vendor;
        }

        if ($vendorUser && $vendorUser instanceof \App\Models\Vendor) {
            return $vendorUser;
        }

        return null;
    }
    
    /**
     * Get the acting store - works for both vendor and employee authentication
     * 
     * @return \App\Models\Store|null
     */
    protected function getActingStore()
    {
        $vendor = $this->getActingVendor();
        return $vendor ? $vendor->store()->first() : null;
    }
    
    /**
     * Check if current user is a vendor (not employee)
     *
     * @return bool
     */
    protected function isVendor()
    {
        $user = Auth::guard('vendor_api')->user();
        return $user && $user instanceof \App\Models\Vendor;
    }

    /**
     * Check if current user is an employee (not vendor)
     *
     * @return bool
     */
    protected function isEmployee()
    {
        $user = Auth::guard('vendor_employee_api')->user();
        return $user && $user instanceof \App\Models\VendorEmployee;
    }
    
    /**
     * Get the current authenticated user (vendor or employee)
     * 
     * @return \App\Models\Vendor|\App\Models\VendorEmployee|null
     */
    protected function getCurrentUser()
    {
        if (Auth::guard('vendor_api')->check()) {
            return Auth::guard('vendor_api')->user();
        }
        
        if (Auth::guard('vendor_employee_api')->check()) {
            return Auth::guard('vendor_employee_api')->user();
        }
        
        return null;
    }
}
