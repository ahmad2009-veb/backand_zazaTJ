<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\RestaurantLogic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\RestaurantStoreRequest;
use App\Http\Requests\Api\v3\admin\RestauranUpdateRequest;
use App\Http\Resources\Admin\MainRestaurantResource;
use App\Http\Resources\Admin\RestaurantAdminResource;
use App\Http\Resources\Admin\RestaurantItemResource;
use App\Http\Resources\Admin\RestaurantResource;
use App\Http\Resources\Admin\RestaurantShowResource;
use App\Http\Resources\Admin\Selectbox\StoreResource;
use App\Http\Resources\Admin\Selectbox\WarehouseResource;
use App\Http\Resources\Admin\Store\StoreWarehouseResource;
use App\Models\Restaurant;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Models\Category;
use App\Models\TransactionCategory;
use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log;  

class RestaurantController extends Controller
{
    public function index()
    {
        return RestaurantResource::collection(Restaurant::all());
    }

    public function mainRestaurants()
    {
//        $restaurants = Restaurant::query()->where('main_restaurant_id', null)->get();
//        MainRestaurantResource::collection($restaurants);
        $stores = Store::all();
        $warehouses = Warehouse::all();


        return response()->json([
            'stores' => StoreResource::collection($stores),
            'warehouses' => WarehouseResource::collection($warehouses)
        ]);
    }


    public function list(Request $request)
    {
//        $search = $request->search ?? '';
//        return RestaurantAdminResource::collection(Restaurant::with('zone')
//            ->when($search != '', function ($query) use ($search) {
//                $query->where('name', 'like', '%' . $search . '%');
//            })
//            ->paginate($request->per_page ?? 12));

        $search = $request->get('search');
        $stores = Store::query()->when($search, function ($query) use ($search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->paginate($request->get('per_page', 12));
        return RestaurantAdminResource::collection($stores);
    }

    public function getById(Store $store)
    {
        return RestaurantShowResource::make($store);
    }

    public function exportData(Request $request)
    {

        $request->validate([
            'type' => 'required|in:id,date,all',
            'from_id' => 'required_if:type,id',
            'to_id' => 'required_if:type,id',
            'from_date' => 'required_if:type,date|date_format:Y-m-d',
            'to_date' => 'required_if:type,date|date_format:Y-m-d',
        ]);


        $vendors = Vendor::with('stores')
            ->when($request['type'] == 'date', function ($query) use ($request) {
                $query->whereBetween('created_at',
                    [$request['from_date'], $request['to_date']]);
            })
            ->when($request['type'] == 'id', function ($query) use ($request) {
                $query->whereBetween('id', [$request['from_id'], $request['to_id']]);
            })
            ->get();
        return (new FastExcel(RestaurantLogic::format_export_restaurants($vendors)))->download('stores.xlsx');
    }


    public function store(RestaurantStoreRequest $request)
    {

//        if ($request->zone_id) {
//            $point = new Point($request->latitude, $request->longitude);
//            $zone = Zone::query()->find($request->zone_id)->whereContains('coordinates', $point)->where('id', $request->zone_id)->get();
//            if ($zone->isEmpty()) {
//                return response()->json(['message' => trans('messages.coordinates_out_of_zone')], 400);
//            }
//        }
//        if ($request->has('main_restaurant_id')) {
//            $main_restaurant = Restaurant::query()->where('id', $request->main_restaurant_id)->first();
//        }

            $vendor = new Vendor();
            $vendor->f_name = $request->f_name;
            $vendor->l_name = $request->l_name ?? null;
            $vendor->email = $request->email;
            $vendor->phone = $request->phone;
            $vendor->password = bcrypt($request->password);
            $vendor->save();

        try {
            $store = new Store();
            $store->name = isset($main_restaurant) ? $main_restaurant->name : $request->name;
            $store->phone = $request->phone;
            $store->email = $request->email;
            $store->main_restaurant_id = $request->main_restaurant_id ?? null;
            $store->logo = isset($main_restaurant) ? $main_restaurant->logo : Helpers::upload('restaurant/', 'png', $request->file('logo'));
            $store->cover_photo = isset($main_restaurant) ? $main_restaurant->cover_photo : Helpers::upload('restaurant/cover/', 'png', $request->file('cover_photo'));
            $store->address = $request->address;
            $store->map_link = $request->map_link ?? null;
            $store->latitude = $request->latitude ?? null;
            $store->longitude = $request->longitude ?? null;
            $store->vendor_id = $vendor->id;
//            $store->zone_id = $request->zone_id;
//            $store->tax = isset($main_restaurant) ? $main_restaurant->tax : $request->tax;
            $store->tax = $request->tax ?? 0;
            $store->delivery_time = $request->minimum_delivery_time . '-' . $request->maximum_delivery_time;
            $store->opening_time = null;
            $store->closing_time = null;

            $store->save();
            // TODO: Move to config 
            $categories = [
                'Женщинам' => [
                    'Блузки и рубашки','Брюки','Верхняя одежда','Джемперы, водолазки и кардиганы',
                    'Джинсы','Костюмы','Пиджаки, жилеты и жакеты','Платья и сарафаны',
                    'Толстовки, свитшоты и худи','Туники','Футболки и топы','Халаты',
                    'Шорты','Юбки','Белье','Одежда для дома','Свадьба','Другое',
                ],
                'Обувь' => [
                    'Детская','Для новорождённых','Женская','Мужская','Спецобувь','Другое',
                ],
                'Детям' => [
                    'Для девочек','Для мальчиков','Для новорождённых','Детская электроника','Детский транспорт','Другое',
                ],
                'Мужчинам' => [
                    'Брюки','Верхняя одежда','Джемперы, водолазки и кардиганы','Джинсы','Костюмы',
                    'Майки','Пиджаки, жилеты и жакеты','Пижамы','Рубашки','Толстовки, свитшоты и худи',
                    'Футболки','Футболки-поло','Халаты','Шорты','Бельё','Одежда для дома','Свадьба','Другое',
                ],
                'Дом' => [
                    'Ванная','Кухня','Предметы интерьера','Спальня','Гостиная','Детская',
                    'Досуг и творчество','Бытовая химия','Другое',
                ],
                'Красота' => [
                    'Аксессуары','Волосы','Аптечная косметика','Для загара','Корейские бренды','Другое',
                ],
                'Аксессуары' => [
                    'Аксессуары для волос','Аксессуары для одежды','Бижутерия','Ювелирные изделия',
                    'Веера','Галстуки и бабочки','Головные уборы','Зеркальца','Зонты',
                    'Кошельки и кредитницы','Носовые платки','Очки и футляры',
                    'Перчатки и варежки','Платки и шарфы','Другое',
                ],
                'Электроника' => [
                    'Автоэлектроника и навигация','Гарнитуры и наушники','Детская электроника',
                    'Игровые консоли и игры','Кабели и зарядные устройства','Музыка и видео',
                    'Ноутбуки и компьютеры','Офисная техника','Развлечения и гаджеты',
                    'Сетевое оборудование','Системы безопасности','Смартфоны и телефоны',
                    'Смарт-часы и браслеты','ТВ, Аудио, Фото, Видео техника','Другое',
                ],
                'Игрушки' => [
                    'Антистресс','Для малышей','Для песочницы','Игровые комплексы','Игровые наборы',
                    'Игрушечное оружие и аксессуары','Игрушечный транспорт','Игрушки для ванной',
                    'Интерактивные','Конструкторы','Куклы и аксессуары','Музыкальные','Другое',
                ],
                'Мебель' => [
                    'По помещениям','Мебель для хранения','Бескаркасная мебель','Детская мебель',
                    'Диваны и кресла','Матрасы','Столы и стулья','Компьютерная и геймерская мебель',
                    'Мебель для гостиной','Мебель для кухни','Мебель для прихожей',
                    'Мебель для спальни','Гардеробная мебель','Офисная мебель','Садовая мебель','Другое',
                ],
                'Бытовая техника' => [
                    'Климатическая техника','Красота и здоровье','Садовая техника',
                    'Техника для дома','Техника для кухни','Крупная бытовая техника','Другое',
                ],
                'Спорт' => [
                    'Фитнес и тренажёры','Велоспорт','Йога / Пилатес','Охота и рыбалка',
                    'Самокаты / Ролики / Скейтборды','Туризм / Походы','Бег / Ходьба','Командные виды спорта','Другое',
                ],
                'Автотовары' => [
                    'Шины и диски колесные','Запчасти','Масла и жидкости','Автокосметика и автохимия',
                    'Краски и грунтовки','Автоэлектроника и навигация','Аккумуляторы и сопутствующие товары',
                    'Аксессуары в салон и багажник','Коврики','Внешний тюнинг','Инструменты','Другое',
                ],
                'Книги' => [
                    'Художественная литература','Комиксы и манга','Книги для детей',
                    'Воспитание и развитие ребёнка','Образование','Самообразование и развитие',
                    'Бизнес и менеджмент','Другое',
                ],
                'Для ремонта' => [
                    'Колеровка краски','Двери, окна и фурнитура','Инструменты и оснастка',
                    'Силовая техника и оборудование','Спецодежда и СИЗы','Отделочные материалы',
                    'Электрика','Другое',
                ],
                'Канцтовары' => [
                    'Анатомические модели','Бумажная продукция','Карты и глобусы',
                    'Офисные принадлежности','Письменные принадлежности','Рисование и лепка',
                    'Счётный материал','Торговые принадлежности','Чертёжные принадлежности','Другое',
                ],
                'Акции' => ['Другое'],
            ];
        
            // 2. В транзакции создаём родительские категории, а затем их подкатегории
            if($vendor){
                TransactionCategory::create([
                    'name' => 'Реализация',
                    'parent_id' => 0,
                    'vendor_id' => $vendor->id,
                ]);
            }
            try {
                DB::transaction(function () use ($categories, $store) {
                    foreach ($categories as $parentName => $subcats) {
                        $parent = Category::create([
                            'name'      => $parentName,
                            'parent_id' => 0,
                            'store_id'  => $store->id,
                            'position'  => 0,
                            'status'    => 1,
                        ]);
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
                });
            } catch (\Exception $e) {
               
                Log::error('Ошибка создания категорий для магазина ' . $store->id . ': ' . $e->getMessage());
                return response()->json([
                    'message' => 'Не удалось создать категории: ' . $e->getMessage()
                ], 500);
            }
        
  

            try {
                $store->employeeRoles()->create([
                    'name' => 'Кассир',
                    'modules' => json_encode(['order', 'pos']),
                    'status' => 1,
                ]);
            } catch (Error $error) {
                $store->delete();
                $store->employeeRoles()->delete();
            }
        } catch (Error $error) {
            $vendor->delete();
        }

        return response()->json(['message' => 'Магазин и категории успешно созданы'], 201);
    }

    public function update(RestauranUpdateRequest $request, Store $store)
    {
//        if ($request->zone_id) {
//            $point = new Point($request->latitude, $request->longitude);
//            $zone = Zone::query()->whereContains('coordinates', $point)->where('id', $request->zone_id)->first();
//            if (!$zone) {
//                return response()->json(['message' => trans('messages.coordinates_out_of_zone')]);
//            }
//        }
//
//        if ($request->has('main_restaurant_id')) {
//            $main_restaurant = Restaurant::query()->where('id', $request->main_restaurant_id)->first();
//        }


        $vendor = Vendor::findOrFail($store->vendor->id);
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->email = $request->email;
        $vendor->phone = $request->phone;
        $vendor->password = $request->has('password') ? bcrypt($request->password) : $store->vendor->password;
        $vendor->save();

        $store->name = isset($main_restaurant) ? $main_restaurant->name : $request->name;
        $store->phone = $request->phone;
        $store->email = $request->email;
        if ($request->hasFile('logo')) {
            if (Storage::disk('public')->exists('restaurant/' . $store['logo'])) {
                Storage::disk('public')->delete('restaurant/' . $store['logo']);
            }
            $store->logo = Helpers::upload('restaurant/', 'png', $request->file('logo'));
        }
        if ($request->hasFile('cover_photo')) {
            if (Storage::disk('public')->exists('restaurant/cover/' . $store['cover_photo'])) {
                Storage::disk('public')->delete('restaurant/cover/' . $store['cover_photo']);
            }
            $store->cover_photo = Helpers::upload('restaurant/cover/', 'png', $request->file('cover_photo'));
        }
        $store->address = $request->address;
        $store->map_link = $request->map_link;
        $store->latitude = $request->latitude;
        $store->longitude = $request->longitude;
        $store->vendor_id = $vendor->id;
        $store->main_restaurant_id = $request->main_restaurant_id;
        $store->zone_id = $request->zone_id;
        $store->tax = isset($main_restaurant) ? $main_restaurant->tax : $request->tax;
        $store->delivery_time = $request->minimum_delivery_time . '-' . $request->maximum_delivery_time;

        $store->save();

//        if (!isset($main_restaurant)) {
//            $store->subStores()->update([
//                'name' => $store->name,
//                'tax' => $store->tax,
//                'logo' => $store->logo,
//                'cover_photo' => $store->cover_photo,
//            ]);
//        }
        return response()->json(['message' => 'Магазин успешно обновлен'], 201);
    }


    public function delete(Store $store)
    {
        if (Storage::disk('public')->exists('restaurant/' . $store['logo'])) {
            Storage::disk('public')->delete('restaurant/' . $store['logo']);
        }
        $store->delete();

        $vendor = Vendor::findOrFail($store->vendor->id);
        if ($vendor) {
            $vendor->delete();
        }


        return response()->json(['message' => "Магазин успешно удален"]);
    }

    public function updateStatus(Request $request, Store $store)
    {

        $request->validate([
            'status' => 'required|boolean',
        ]);
        $store->status = $request->status;
        $store->save();
        return response()->json(['message' => 'status updated successfully']);
    }


}
