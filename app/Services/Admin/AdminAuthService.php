<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Auth;

class AdminAuthService
{
    public function login(array $data): array|\Illuminate\Http\JsonResponse
    {
        if (Auth::guard('admin')->attempt($data)) {
            $admin = Auth::guard('admin')->user();
            $token = $admin->createToken('AdminAuth');
            return ['token' => $token->accessToken, 'user' => $admin, 'type' => 'admin'];
        }

        if (Auth::guard('vendor')->attempt($data)) {
            $vendor = Auth::guard('vendor')->user();
            if (!$vendor->store->status) {
                return response()->json(['message' => trans('messages.inactive_vendor_warning')], 403);
            }

            $token = $vendor->createToken('VendorAuth:' . $vendor->id);
            return ['token' => $token->accessToken, 'user' => $vendor, 'type' => 'vendor'];
        }

        return response()->json(['message' => 'invalid credentials'], 401);
    }
}
