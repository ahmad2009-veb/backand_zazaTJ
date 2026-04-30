<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProfileService;
use App\Services\Admin\AdminAuthService;
use App\Http\Resources\Admin\AdminResource;
use App\Http\Requests\Api\v3\admin\AdminLoginRequest;
use App\Http\Requests\Api\V3\Admin\UpdateProfileRequest;

class AuthController extends Controller
{
    public function __construct(protected AdminAuthService $authService) {}

    public function login(AdminLoginRequest $request)
    {
        return $this->authService->login($request->validated());
    }

    public function logout()
    {
        $admin = auth()->user();
        $token = $admin->token();
        $token->delete();

        return response()->json(['message' => 'logged out '], 200);
    }

    public function getAdmin()
    {
        return AdminResource::make(auth()->user());
    }

    public function getPermissions()
    {
        $admin = auth()->user();
        return response()->json(json_decode($admin->role->modules) ?? 'full-access');
    }

    public function edit(UpdateProfileRequest $request, ProfileService $profileService)
    {
        $admin = auth()->user();
        $profileService->updateAdminProfile($admin, $request->validated());

        return response()->json(['message' => 'Admin updated successfully']);
    }
}
