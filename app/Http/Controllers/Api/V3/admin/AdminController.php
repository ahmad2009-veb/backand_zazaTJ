<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\StoreAdminRequest;
use App\Http\Requests\Api\v3\admin\UpdateAdminRequest;
use App\Http\Resources\Admin\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $admins = Admin::query()
            ->where('role_id', '!=', 1)
            ->when($search, function ($query) use ($search) {
                $query->where('f_name', 'like', '%' . $search . '%')
                    ->orWhere('l_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            })
            ->paginate($request->input('per_page') ?? 12);

        return AdminResource::collection($admins);
    }

    public function store(StoreAdminRequest $request)
    {
        if ($request->role_id == 1) {
            return response()->json(['message' => trans('messages.access_denied')], 403);
        }

        $admin = Admin::query()->create([
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'image' => Helpers::upload('admin/', 'png', $request->file('image')),
            'role_id' => $request->role_id,
            'password' => bcrypt($request->password),
        ]);

        return new AdminResource($admin);
    }

    public function update(UpdateAdminRequest $request, Admin $admin)
    {
        if ($request->role_id == 1) {
            return response()->json(['message' => trans('messages.access_denied')], 403);
        }

        $admin->update([
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'image' => Helpers::update('admin/', $admin->image, 'png', $request->file('image')),
            'role_id' => $request->role_id,
            'password' => $request->has('password') ? bcrypt($request->password) : $admin->password,
        ]);

        return response()->json([
            'message' => 'Admin updated successfully'
        ]);
    }

    public function destroy(Admin $admin)
    {
        if ($admin->role_id == 1) {
            return response()->json(['message' => trans('messages.access_denied')], 403);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully'
        ]);
    }
}
