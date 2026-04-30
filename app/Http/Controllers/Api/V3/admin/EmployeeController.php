<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\EmployeeStoreRequest;
use App\Http\Requests\Api\v3\admin\EmployeeUpdateRequest;
use App\Http\Resources\Admin\EmployeeResource;
use App\Models\Admin;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;


class EmployeeController extends Controller
{
    public function index(Request $request)
    {

        $search = $request->input('search', '');
        $keywords = explode(' ', $search);

        $em = Admin::zone()->with(['role'])->where('role_id', '!=',
            '1')
            ->when(count($keywords) > 0, function ($query) use ($keywords) {
                $query->where(function ($query) use ($keywords) {
                    foreach ($keywords as $value) {
                        $query->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%"); // Assuming 'email' is a searchable field
                    }
                });
            })
            ->latest()->paginate($request->input('per_page'));

        return EmployeeResource::collection($em);
    }

    public function store(EmployeeStoreRequest $request)
    {
        if ($request->role_id == 1) {
            return response()->json(['message' => trans('messages.access_denied')]);
        }

        DB::table('admins')->insert([
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'phone' => $request->phone,
            'zone_id' => $request->zone_id,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => bcrypt($request->password),
            'image' => Helpers::upload('admin/', 'png', $request->file('image')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => trans('messages.employee_added_successfully')]);
    }

    public function update(EmployeeUpdateRequest $request, $id)
    {

        if ($request->role_id == 1) {
            return response()->json(['message' => trans('messages.access_denied')]);
        }

        $e = Admin::where('role_id', '!=', 1)->findOrFail($id);

        if ($request['password'] == null) {
            $pass = $e['password'];
        } else {
            if (strlen($request['password']) < 6) {
                return response()->json(['message' => trans('messages.password_length_warning', ['length' => '6'])],401);
            }
            $pass = bcrypt($request->password);
        }

        if ($request->has('image')) {
            $e['image'] = Helpers::update('admin/', $e->image, 'png', $request->file('image'));
        }

        DB::table('admins')->where(['id' => $id])->update([
            'f_name'     => $request->f_name,
            'l_name'     => $request->l_name,
            'phone'      => $request->phone,
            'zone_id'    => $request->zone_id,
            'email'      => $request->email,
            'role_id'    => $request->role_id,
            'password'   => $pass,
            'image'      => $e['image'],
            'updated_at' => now(),
        ]);

        return response()->json(['message' => trans('messages.employee_updated_successfully')]);

    }

    public function delete($id) {
        $role = Admin::zone()->where('role_id', '!=', '1')->where(['id' => $id])->delete();
        return response()->json(['message' => trans('messages.employee_deleted_successfully')]);
    }

    public function exportData() {
        $employess = $role = Admin::zone()->where('role_id', '!=', '1')->get();
        return (new FastExcel($this->exportEmployes($employess)))->download('employeess.xlsx');
    }

    private function exportEmployes($employess)
    {
        $storage = [];
        foreach ($employess as $item) {
            $storage[] = [
                'id' => $item->id,
                'f_name' => $item->f_name,
                'l_name' => $item->l_name,
                'phone' => $item->phone,
                'email' => $item->email,
                'role_id' => $item->role_id,
                'zone_id' => $item->zone_id,
                'restaurant_id' => $item->restaurant_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        }


        return $storage;
    }


}
