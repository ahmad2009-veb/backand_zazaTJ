<?php

namespace App\Http\Controllers\Api\V3\vendor\auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\Vendor\VendorStoreRequest;
use App\Http\Resources\Admin\VendorEmployeeUserResource;
use App\Http\Resources\Admin\VendorUserResource;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Category;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $vendor = Vendor::where('email', $request->email)->first();

        if (!$vendor) {
            return response()->json(['message' => 'Неправильный email или пароль'], 401);
        }
        if ($vendor) {

            if ($vendor->store->status == 0) {
                return response()->json(['message' => trans('messages.inactive_vendor_warning')]);
            }


            if (auth('vendor')->attempt(
                ['email' => $request->email, 'password' => $request->password],
                $request->remember
            )) {
                $vendor = Auth::guard('vendor')->user();
                $token = $vendor->createToken('VendorAuth:' . $vendor->id);
                return ['token' => $token->accessToken, 'user' => $vendor, 'type' => 'vendor'];
            }

            return response()->json(['message' => 'Неверные учетные данные'], 401);
        }
    }

    public function loginEmployee(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|min:6',
        ]);

        $cleanPhone = preg_replace('/\s+/', '', $request->phone);

        if (!preg_match('/^\+992\d{9}$/', $cleanPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number must start with +992 and be followed by 9 digits.',
                'errors' => ['phone' => ['Phone number must start with +992 and be followed by 9 digits.']]
            ], 422);
        }

        $vendorEmployee = VendorEmployee::where('phone', $cleanPhone)->first();

        if (!$vendorEmployee) {
            return response()->json(['message' => 'Неправильный номер телефона или пароль'], 401);
        }

        // if ($vendorEmployee->restaurant->status == 0) {
        //     return response()->json(['message' => trans('messages.inactive_vendor_warning')]);
        // }

        if (auth('vendor_employee')->attempt(
            ['phone' => $cleanPhone, 'password' => $request->password],
            $request->remember
        )) {
            $authenticatedEmployee = Auth::guard('vendor_employee')->user();

            $vendorEmployee = VendorEmployee::find($authenticatedEmployee->id);

            $token = $authenticatedEmployee->createToken('EmployeeAuth:' . $authenticatedEmployee->id);


            return [
                'token' => $token->accessToken,
                'user' => $vendorEmployee,
                'modules' => $vendorEmployee->getModules(),
                'user_type' => 'employee'
            ];
        }

        return response()->json(['message' => 'Неверные учетные данные'], 401);
    }

    public function logout(Request $request)
    {
        $vendor = auth()->user();

        $token = $vendor->token();
        $token->delete();
        return  response()->json(['message' => 'Выход выполнен'], 200);
    }

    public function getVendor(Request $request)
    {

        return VendorUserResource::make(auth()->user());
    }

    public function getVendorEmployee(Request $request)
    {
        return VendorEmployeeUserResource::make(auth()->user());
    }

    public function getPermissions(Request $request)
    {
        $authenticatedUser = auth()->user();

        if ($authenticatedUser instanceof \App\Models\VendorEmployee) {
            return response()->json([
                'modules' => $authenticatedUser->getModules(),
            ]);
        }

        if ($authenticatedUser instanceof \App\Models\Vendor) {
            return response()->json('full-access');
        }

        return response()->json('full-access');
    }

    public function registration(VendorStoreRequest $request)
    {
        try {
            // 1. Открываем транзакцию, чтобы при любой ошибке всё откатилось
            DB::beginTransaction();
    
            // 2. Создаём вендора
            $vendor = Vendor::query()->create([
                'f_name'   => $request->input('f_name'),
                'phone'    => $request->input('phone'),
                'email'    => $request->input('email'),
                'password' => bcrypt($request->password),
            ]);
    
            // 3. Создаём магазин и запоминаем модель для связи категорий
            $store = Store::query()->create([
                'name'      => $request->name,
                'address'   => $request->address,
                'vendor_id' => $vendor->id,
                'phone'     => $vendor->phone,
            ]);
    
            // 4. Список категорий и подкатегорий
            $categories = [
                'Женщинам' => [
                    'Блузки и рубашки','Брюки','Верхняя одежда','Джемперы, водолазки и кардиганы',
                    'Джинсы','Костюмы','Пиджаки, жилеты и жакеты','Платья и сарафаны',
                    'Толстовки, свитшоты и худи','Туники','Футболки и топы','Халаты',
                    'Шорты','Юбки','Белье','Одежда для дома','Свадьба','Другое',
                ],
                'Обувь'    => ['Детская','Для новорождённых','Женская','Мужская','Спецобувь','Другое'],
                'Детям'    => ['Для девочек','Для мальчиков','Для новорождённых','Детская электроника','Детский транспорт','Другое'],
                'Мужчинам' => ['Брюки','Верхняя одежда','Джемперы, водолазки и кардиганы','Джинсы','Костюмы',
                               'Майки','Пиджаки, жилеты и жакеты','Пижамы','Рубашки','Толстовки, свитшоты и худи',
                               'Футболки','Футболки-поло','Халаты','Шорты','Бельё','Одежда для дома','Свадьба','Другое'],
                // … добавьте остальные категории аналогичным образом …
            ];
    
            // 5. Создаём категории внутри той же транзакции
            foreach ($categories as $parentName => $subcats) {
                // создаём родительскую категорию
                $parent = Category::create([
                    'name'      => $parentName,
                    'parent_id' => 0,
                    'store_id'  => $store->id,
                    'position'  => 0,
                    'status'    => 1,
                ]);
    
                // для каждой подкатегории создаём запись с parent_id = ID родителя
                foreach ($subcats as $childName) {
                    Category::create([
                        'name'      => $childName,
                        'parent_id' => $parent->id,
                        'store_id'  => $store->id,
                        'position'  => 0,
                        'status'    => 1,
                    ]);
                }
            }
    
            
            DB::commit();
    
            return response()->json(['message' => 'Магазин и категории успешно созданы'], 201);
    
        } catch (\Exception $ex) {
          
            DB::rollBack();

            return response()->json([
                'message' => 'Ошибка при регистрации: ' . $ex->getMessage()
            ], $ex->getCode() ?: 500);
        }
    
    }
}
