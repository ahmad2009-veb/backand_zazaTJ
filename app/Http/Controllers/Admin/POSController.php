<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cart\CreateCartItemAction;
use App\Actions\Cart\ForgetCartAction;
use App\Actions\Cart\GetCartAction;
use App\Actions\Cart\RemoveCartItemAction;
use App\Actions\Cart\SetCustomDeliveryChargeAction;
use App\Actions\Cart\UpdateCartItemAction;
use App\Actions\Product\GetProductDataAction;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pos\PlaceOrderRequest;
use App\Http\Requests\Admin\Pos\StoreCustomerAddressRequest;
use App\Http\Requests\Admin\Pos\StoreCustomerRequest;
use App\Models\Category;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

class POSController extends Controller
{
    public function index(Request $request)
    {
        return view('admin-views.pos.index');
    }

    public function zones()
    {
        $zones = Zone::active()->orderBy('name')->get();
        foreach ($zones as $zone) {
            $zone->coordinates = mb_convert_encoding($zone->name, 'UTF-8', 'UTF-8');
        }
        return $zones;
    }

    public function restaurants(Zone $zone)
    {
        return Restaurant::active()
            ->where('zone_id', $zone->id)
            ->orderBy('name')
            ->get();
    }

    public function categories()
    {
        return Category::active()->get();
    }

    public function foods(Request $request)
    {
        $time       = Carbon::now()->toTimeString();
        $restaurant = Restaurant::find($request->restaurant_id);

        return Food::active()
            ->where('restaurant_id', $request->restaurant_id)
            ->when($request->category_id, function ($query, $category) {
                $query->whereHas(
                    'category',
                    fn($query) => $query->whereId($category)->orWhere('parent_id', $category)
                );
            })
            ->when($request->q, function ($query, $searchQuery) {
                $keys = explode(' ', preg_replace('/\s+/', ' ', $searchQuery));

                return $query->where(function ($query) use ($keys) {
                    foreach ($keys as $value) {
                        $query->orWhere('name', 'LIKE', "%{$value}%");
                    }
                });
            })
            //->available($time)
            ->latest()
            ->paginate(10)
            ->through(fn($food) => [
                'id'    => $food->id,
                'name'  => Str::limit($food->name, 12),
                'price' => Helpers::format_currency($food->price - Helpers::product_discount_calculate(
                        $food, $food->price, $restaurant
                    )
                ),
                'image' => asset("storage/product/{$food['image']}"),
            ]);
    }

    public function foodDetails(Food $food)
    {
        $product = app(GetProductDataAction::class)->execute($food);

        $options = array_map(
            fn(array $option) => $option['options'][0],
            Arr::get($product, 'options', [])
        );

        $extra = array_map(
            fn(array $extra) => ['id' => $extra['id'], 'quantity' => 0],
            Arr::get($product, 'extra', [])
        );

        return [
            'product' => $product,
            'options' => $options,
            'extra'   => $extra,
        ];
    }

    public function cart()
    {
        return app(GetCartAction::class)->execute('admin_cart');
    }

    public function clearCart()
    {
        app(ForgetCartAction::class)->execute('admin_cart');
    }

    public function addToCart(Request $request)
    {
        return app(CreateCartItemAction::class)->execute([
            'product'  => $request->get('product'),
            'quantity' => $request->get('quantity'),
            'options'  => $request->get('options', []),
            'extra'    => $request->get('extra', []),
        ], 'admin_cart');
    }

    public function removeCartItem(string $id)
    {
        return app(RemoveCartItemAction::class)->execute($id, 'admin_cart');
    }

    public function updateCartItem(Request $request, string $id)
    {
        return app(UpdateCartItemAction::class)->execute($id, $request->get('item'), 'admin_cart');
    }

    public function getCustomers(Request $request)
    {
        $keys = explode(' ', preg_replace('/\s+/', ' ', $request['q']));

        $data = User::query()
            ->when($keys, function ($query, $keys) {
                $query->where(function ($query) use ($keys) {
                    foreach ($keys as $value) {
                        $query
                            ->orWhere('f_name', 'LIKE', "%{$value}%")
                            ->orWhere('l_name', 'LIKE', "%{$value}%")
                            ->orWhere('phone', 'LIKE', "%{$value}%");
                    }
                });
            })
            ->limit(8)
            ->get()
            ->transform(fn(User $user) => [
                'id'   => $user->id,
                'text' => $user->name_with_phone,
            ]);

        return response()->json($data);
    }

    public function storeCustomer(StoreCustomerRequest $request)
    {
        $customer = User::create([
            'f_name'   => $request->get('f_name'),
            'l_name'   => $request->get('l_name'),
            'email'    => $request->get('email'),
            'phone'    => $request->get('phone'),
            'password' => bcrypt(Str::random()),
        ]);

        return [
            'id'              => $customer->id,
            'name_with_phone' => $customer->name_with_phone,
        ];
    }

    public function customersAddresses(User $customer)
    {
        return $customer->addresses;
    }

    public function storeCustomerAddress(User $customer, StoreCustomerAddressRequest $request)
    {
        $point = new Point($request->latitude, $request->longitude);
        $zones = Zone::whereContains('coordinates', $point)->get(['id']);

        return $customer->addresses()->create([
            'contact_person_number' => $customer->phone,
            'address_type'          => $request->address_type,
            'road'                  => $request->road,
            'house'                 => $request->house,
            'longitude'             => $request->longitude,
            'latitude'              => $request->latitude,
            'zone_id'               => $zones->first()->id,
        ]);
    }

    public function setCustomDeliveryCharge(Request $request)
    {
        return app(SetCustomDeliveryChargeAction::class)->execute($request->get('custom_delivery_charge', 0), 'admin_cart');
    }

    public function placeOrder(PlaceOrderRequest $request)
    {
        $customer = User::find($request->customer_id);

        $order = OrderLogic::placeOrderPos(
            cart: $request->cart,
            order_type: $request->cart['order_type'],
            payment_method: 'cash_on_delivery',
            address_id: $request->address_id,
            contact: [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
            ],
            notes: $request->order_notes,
        );

        app(ForgetCartAction::class)->execute('admin_cart');

        return response()->json(['order' => $order->id]);
    }

    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            'l_name' => 'required',
            'email'  => 'required|email|unique:users',
            'phone'  => 'required|unique:users',
        ]);
        User::create([
            'f_name'   => $request['f_name'],
            'l_name'   => $request['l_name'],
            'email'    => $request['email'],
            'phone'    => $request['phone'],
            'password' => bcrypt('password'),
        ]);
        try {
            if (config('mail.status')) {
                Mail::to($request->email)->send(new \App\Mail\CustomerRegistration($request->f_name . ' ' . $request->l_name,
                    true));
            }
        } catch (\Exception $ex) {
            info($ex);
        }
        Toastr::success(translate('customer_added_successfully'));

        return back();
    }
}
