<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminRoleResource;
use App\Models\AdminRole;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    public function index()
    {
        $roles = AdminRole::query()->where('id', '!=', 1)->get();
        return AdminRoleResource::collection($roles);
    }
}
