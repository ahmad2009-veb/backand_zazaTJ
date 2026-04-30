<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorEmployeeAccess
{
    /**
     * Handle an incoming request.
     * This middleware should be used AFTER auth middleware to check employee module permissions
     * Vendors always have access, employees need specific modules
     */
    public function handle(Request $request, Closure $next, ...$requiredModules)
    {
        // If user is authenticated as vendor, always allow access
        if (Auth::guard('vendor_api')->check()) {
            return $next($request);
        }

        // If user is authenticated as employee, check module permissions
        if (Auth::guard('vendor_employee_api')->check()) {
            $authenticatedUser = Auth::guard('vendor_employee_api')->user();

            // Make sure we have the VendorEmployee model instance
            $employee = \App\Models\VendorEmployee::find($authenticatedUser->id);
            $employeeModules = $employee->getModules();

            // If no specific modules required, allow access
            if (empty($requiredModules)) {
                return $next($request);
            }

            // Check if employee has any of the required modules
            foreach ($requiredModules as $module) {
                if (in_array($module, $employeeModules)) {
                    return $next($request);
                }
            }

            // Employee doesn't have required module access
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Required permissions: ' . implode(', ', $requiredModules),
                'required_modules' => $requiredModules,
                'your_modules' => $employeeModules
            ], 403);
        }

        // This middleware should only be used after auth middleware
        // If we reach here, user is not authenticated at all
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
    }
}
