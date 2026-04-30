<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\Vendor\StoreEmployeeRequest;
use App\Http\Requests\Api\v3\Vendor\UpdateEmployeeRequest;
use App\Http\Resources\Vendor\EmployeeResource;
use App\Models\VendorEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $vendor = auth()->user();
        $search = $request->input('search', '');
        $employees = $vendor->employees()
            ->when($search, function ($query) use ($search) {
                $query->where('f_name', 'like', '%' . $search . '%')
                    ->orWhere('l_name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            })
            ->paginate($request->input('per_page') ?? 12);

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request)
    {
        DB::beginTransaction();
        try {
            $vendor = auth()->user();

            // Clean phone number (remove spaces)
            $cleanPhone = preg_replace('/\s+/', '', $request['phone']);

            // Validate phone format
            if (!preg_match('/^\+992\d{9}$/', $cleanPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number must start with +992 and be followed by 9 digits.',
                    'errors' => ['phone' => ['Phone number must start with +992 and be followed by 9 digits.']]
                ], 422);
            }

            // Prepare employee data
            $employeeData = [
                'f_name' => $request['f_name'],
                'l_name' => $request['l_name'],
                'phone' => $cleanPhone,
                'vendor_id' => $vendor->id,
                'password' => bcrypt($request['password']),
                'modules' => $request['modules'] ?? [],
            ];

            // Add image if provided
            if ($request->hasFile('image')) {
                $employeeData['image'] = Helpers::upload('admin/', 'png', $request->file('image'));
            }

            $employee = VendorEmployee::query()->create($employeeData);

            DB::commit();
            return EmployeeResource::make($employee);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Employee creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateEmployeeRequest $request, VendorEmployee $vendorEmployee)
    {
        DB::beginTransaction();
        try {
            // Clean phone number (remove spaces)
            $cleanPhone = preg_replace('/\s+/', '', $request['phone']);

            // Validate phone format
            if (!preg_match('/^\+992\d{9}$/', $cleanPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number must start with +992 and be followed by 9 digits.',
                    'errors' => ['phone' => ['Phone number must start with +992 and be followed by 9 digits.']]
                ], 422);
            }

            // Prepare update data
            $updateData = [
                'f_name' => $request['f_name'],
                'l_name' => $request['l_name'],
                'phone' => $cleanPhone,
            ];

            // Handle password update
            if (!empty($request['password'])) {
                $updateData['password'] = bcrypt($request['password']);
            }

            // Handle image update
            if ($request->hasFile('image')) {
                $updateData['image'] = Helpers::update('admin/', $vendorEmployee->image, 'png', $request->file('image'));
            }

            // Handle modules
            if (!empty($request['modules'])) {
                $updateData['modules'] = $request['modules'];
            }

            $vendorEmployee->update($updateData);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => new \App\Http\Resources\Vendor\EmployeeResource($vendorEmployee->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Employee update failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(VendorEmployee $vendorEmployee)
    {
        DB::beginTransaction();
        try {
            $vendorEmployee->delete();

            DB::commit();
            return response()->json([
                'message' => 'Vendor employee deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Employee deletion failed: ' . $e->getMessage()], 500);
        }
    }

}
