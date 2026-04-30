<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\CustomerAddress;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Resources\Admin\CustomerResource;
use App\Http\Resources\Admin\CustomerListResource;
use App\Http\Resources\Admin\CustomerSearchResource;
use App\Http\Requests\Api\v3\admin\UpdateCustomerRequest;


class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $keys   = $search === '' ? [] : array_filter(explode(' ', $search));

        $query = User::query()
            ->where('created_by', auth()->user()?->store?->id)
            ->with(['createdClients', 'orders', 'addresses', 'customerImports'])
            ->withSum(['orders as total_order_amount' => function ($q) {
                $q->whereNotIn('order_status', ['refunded', 'canceled']);
            }], 'order_amount')
            ->withCount(['orders as total_order_count' => function ($q) {
                $q->whereNotIn('order_status', ['refunded', 'canceled']);
            }]);
    

        if (count($keys) > 0) {
            $query->where(function($q) use ($keys) {
                foreach ($keys as $word) {
                    $q->orWhere('f_name', 'like', "%{$word}%")
                      ->orWhere('l_name', 'like', "%{$word}%")
                      ->orWhere('email', 'like', "%{$word}%")
                      ->orWhere('phone', 'like', "%{$word}%");
                }
            });
        }

        // Filter by birthday month
        if ($request->has('birthday') && $request->birthday === 'monthly') {
            $currentMonth = now()->month;
            $query->whereMonth('birth_date', $currentMonth);
        }

        // Filter by new customers (joined this month)
        if ($request->has('type') && $request->type === 'new') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }
    
        $perPage   = min((int)$request->input('per_page', 15), 100);
        $customers = $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    
        return CustomerListResource::collection($customers);
    }
    

    public function getUser(User $user)
    {
        $user->load(['creator', 'orders', 'customerImports']);

        return CustomerResource::make($user);
    }

    public function search(Request $request)
    {
        $customers = User::query()
            ->where('created_by', auth()->user()?->store->id)
            ->limit(8)
            ->where(function($query) use ($request) {
                $query->where('f_name', 'like', '%' . $request->search . '%')
                    ->orWhere('l_name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
            })

            ->with(['addresses', 'orders', 'customerImports'])
            ->get();
        return CustomerSearchResource::collection($customers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string|size:13|unique:users,phone',
            'birthdate' => 'nullable|string|regex:/^\d{2}\.\d{2}\.\d{4}$/',
            'source' => 'nullable|string|max:255',
        ]);

        // Parse birthdate from DD.MM.YYYY format to YYYY-MM-DD
        $birthDate = null;
        if ($request->birthdate) {
            try {
                $birthDate = \Carbon\Carbon::createFromFormat('d.m.Y', $request->birthdate)->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid birthdate format. Expected DD.MM.YYYY'], 422);
            }
        }

        $customer = User::create([
            'f_name' => $request->name,
            'phone' => $request->phone,
            'birth_date' => $birthDate,
            'source' => $request->source ?? null,
            'password' => bcrypt(Str::random(8)),
            'created_by' =>  auth()->user()?->store->id
        ]);

        return CustomerSearchResource::make($customer);
    }

    public function update(UpdateCustomerRequest $request, User $user)
    {
        $user->f_name = $request->f_name;
        $user->l_name = $request->l_name;
        $user->phone = $request->phone;
//        $user->email =  $request->email;
        $user->updated_at = now();
        $user->created_by = $user->created_by ?? Auth::id();
        $user->save();
        if ($request->addresses) {
            $addresses = collect($request->addresses);
            $addresses->each(function ($address) use ($user) {
                CustomerAddress::query()->create([
                    'user_id' => $user->id,
                    'road' => $address['road'] ?? null,
                    'house' => $address['house'] ?? null,
                    'apartment' => $address['apartment'] ?? null,
                    'domofon_code' => $address['domofon_code'] ?? null,
                    'longitude' => $address['longitude'] ?? null,
                    'latitude' => $address['latitude'] ?? null,
                    'address_type' => 'home',
                    'contact_person_number' => $user->phone,
                ]);
            });
        }

        return response()->json(['message' => 'updated successfully'], 201);
    }


    public function delete(User $user)
    {
        $user->addresses()->delete();
        $user->delete();
        return response()->json(['message' => 'user deleted'], 201);
    }

    public function export(string $type)
    {
        $token = request()->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Token is required',
                'message' => 'Authorization token is missing. Please provide a valid token.'
            ], 401);
        }
        
        $user = Auth::guard('vendor_api')->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'The provided token is invalid or expired.'
            ], 401);
        }

        $customers = User::query()
            ->where('created_by', $user->store->id)
            ->select(['id', 'f_name', 'l_name', 'phone', 'email', 'order_count', 'loyalty_points', 'birth_date'])
            ->get();
        $data = [];
        foreach ($customers as $key => $value) {
            $data[] = [
                '№' => $key + 1,
                'id' => $value['id'],
                'f_name' => $value['f_name'],
                'l_name' => $value['l_name'],
                'phone' => $value['phone'],
                'email' => $value['email'],
                'order_count' => $value['order_count'],
                'loyalty_point' => $value['loyalty_points'] ?? 0,
                'birth_date' => $value['birth_date']
            ];
        }
        return (new FastExcel($data))->download('Customers.' . $type);
    }

    public function storeVendor(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address'=> 'required|string',
            'phone' => [
                'required',
                'string',
                'size:13',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('created_by', auth()->user()?->store->id);
                }),
            ],
            'birthdate' => 'nullable|string|regex:/^\d{2}\.\d{2}\.\d{4}$/',
            'source' => 'nullable|string|max:255',
        ]);

        // Parse birthdate from DD.MM.YYYY format to YYYY-MM-DD
        $birthDate = null;
        if ($request->birthdate) {
            try {
                $birthDate = \Carbon\Carbon::createFromFormat('d.m.Y', $request->birthdate)->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid birthdate format. Expected DD.MM.YYYY'], 422);
            }
        }

        $customer = User::create([
            'f_name' => $request->name,
            'user_address' => $request->address,
            'phone' => $request->phone,
            'birth_date' => $birthDate,
            'source' => $request->source ?? null,
            'password' => bcrypt(Str::random(8)),
            'created_by' =>  auth()->user()?->store->id
        ]);
        return CustomerSearchResource::make($customer);
    }

    /**
     * Download customer import template
     */
    public function getTemplate()
    {
        $filePath = 'template/customer_import_template.xlsx';
        if (Storage::disk('public')->exists($filePath)) {
            // Provide a download response
            return Storage::disk('public')->download($filePath);
        }
        return response()->json(['error' => 'Файл не найден.'], 404);
    }
}
