<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\DeliveryMenUpdateRequest;
use App\Http\Requests\Api\v3\DeliveryManStoreRequest;
use App\Http\Resources\Admin\DeliveryManResource;
use App\Http\Resources\Admin\DeliveryManShowResource;
use App\Models\DeliveryMan;
use App\Models\Order;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Traits\VendorEmployeeAccess;

class DeliveryManController extends Controller
{
    use VendorEmployeeAccess;
    public function index(Request $request)
    {   
     
        $keyword = $request->query('search');
        $key = explode(' ', $keyword);
        $deliveryMan = DeliveryMan::query()->when($keyword, function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%")
                    ->orWhere('identity_number', 'like', "%{$value}%");
            }
        })->paginate($request->per_page);

        return DeliveryManResource::collection($deliveryMan);
    }

    public function show(DeliveryMan $deliveryMan)
    {
        return DeliveryManShowResource::make($deliveryMan);
    }

    public function getAvailableDeliveryMans(Request $request, Order $order)
    {


        //        if (isset($order->store) && $order->store->self_delivery_system) {
        //            $deliveryMan = DeliveryMan::query()->where('store_id',$order->store_id)->active()->get();
        //
        //        } else {
        //            if ($order->store !== null) {
        //                $deliveryMan = DeliveryMan::query()->where(
        //                    'store_id',
        //                    $order->store_id
        //                )->active()->get();
        //            } else {
        //                $deliveryMan = DeliveryMan::whereNull('store_id')->active()->get();
        //            }
        //        }
        $deliveryMan = DeliveryMan::query()->whereNot('id', $order->delivery_man_id)->where('status', 1)->get();

        return DeliveryManResource::collection($deliveryMan);
    }


    public function store(DeliveryManStoreRequest $request)
    {

        if ($request->has('image')) {
            $image_name = Helpers::upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $id_img_names = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($id_img_names, $identity_image);
            }
            $identity_image = json_encode($id_img_names);
        } else {
            $identity_image = json_encode([]);
        }

        $dm = new DeliveryMan();
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->phone = $request->phone;
        $dm->identity_number = $request->identity_number;
        $dm->identity_type = $request->identity_type;
        $dm->zone_id = $request->zone_id;
        $dm->identity_image = $identity_image;
        $dm->image = $image_name;
        $dm->active = 0;
        $dm->earning = $request->earning;
        $dm->password = bcrypt($request->password);
        $dm->store_id = $this->getActingVendor()?->store?->id;
        $dm->save();

        return response()->json(['message' => trans('messages.deliveryman_added_successfully')], 201);
    }

    public function update(DeliveryMenUpdateRequest $request, DeliveryMan $deliveryMan)
    {
        if ($request->has('image')) {
            $image_name = Helpers::update('delivery-man/', $deliveryMan->image, 'png', $request->file('image'));
        } else {
            $image_name = $deliveryMan['image'];
        }

        if ($request->has('identity_image')) {
            foreach (json_decode($deliveryMan['identity_image'], true) as $img) {
                if (Storage::disk('public')->exists('delivery-man/' . $img)) {
                    Storage::disk('public')->delete('delivery-man/' . $img);
                }
            }
            $img_keeper = [];
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($img_keeper, $identity_image);
            }
            $identity_image = json_encode($img_keeper);
        } else {
            $identity_image = $deliveryMan['identity_image'];
        }

        $deliveryMan->f_name = $request->f_name;
        $deliveryMan->l_name = $request->l_name;
        $deliveryMan->email = $request->email;
        $deliveryMan->phone = $request->phone;
        $deliveryMan->identity_number = $request->identity_number;
        $deliveryMan->identity_type = $request->identity_type;
        $deliveryMan->zone_id = $request->zone_id;
        $deliveryMan->identity_image = $identity_image;
        $deliveryMan->image = $image_name;
        $deliveryMan->earning = $request->earning;
        $deliveryMan->password = strlen($request->password) > 1 ? bcrypt($request->password) : $deliveryMan['password'];
        $deliveryMan->save();

        return response()->json(['message' => 'updated successfully'], 201);
    }

    public function delete(DeliveryMan $deliveryMan)
    {

        if (Storage::disk('public')->exists('delivery-man/' . $deliveryMan['image'])) {
            Storage::disk('public')->delete('delivery-man/' . $deliveryMan['image']);
        }

        foreach (json_decode($deliveryMan['identity_image'], true) as $img) {
            if (Storage::disk('public')->exists('delivery-man/' . $img)) {
                Storage::disk('public')->delete('delivery-man/' . $img);
            }
        }

        $deliveryMan->userinfo()->delete();
        $deliveryMan->delete();


        return response()->json(['message' => trans('messages.deliveryman_deleted_successfully')], 200);
    }

    public function exportDeliveryMens($type)
    {
        $collection = DeliveryMan::all()

            ->map(function ($deliveryMan) {

                return [
                    'Имя' => $deliveryMan->f_name,
                    'Фамилия' => $deliveryMan->l_name,
                    'Телефон' => $deliveryMan->phone,
                    'Почта' => $deliveryMan->email,
                    'Регистрация' => Carbon::parse($deliveryMan->created_at)->format(('Y-m-d'))
                ];
            });
        if ($type == 'excel') {
            return (new FastExcel($collection))->download('delivery-mens.xlsx');
        } elseif ($type == 'csv') {
            return (new FastExcel($collection))->download('delivery-mens.csv');
        }
    }

    public function selectOptionsVendor()
    {
        $vendor = $this->getActingVendor();
        $deliveryMans =  $vendor->deliveryMans()->where('delivery_men.status', 1)->get();
        return DeliveryManResource::collection($deliveryMans);
    }


    public function indexVendor(Request $request)
    {   
     
        $keyword = $request->query('search');
        $key = explode(' ', $keyword);
        $deliveryMan = DeliveryMan::query()->where('store_id', $this->getActingVendor()?->store->id)->when($keyword, function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%")
                    ->orWhere('identity_number', 'like', "%{$value}%");
            }
        })->paginate($request->per_page);

        return DeliveryManResource::collection($deliveryMan);
    }

    public function selectOptionsAdmin() {
        $deliveryMan = DeliveryMan::query()->where('status', 1)->get();
        return DeliveryManResource::collection($deliveryMan);
    }

    public function getAvailableDeliveryMansVendor(Request $request, Order $order)
    {


     
        $deliveryMan = DeliveryMan::query()->whereNot('id', $order->delivery_man_id)->where('store_id', $this->getActingVendor()->store->id)->where('status', 1)->get();

        return DeliveryManResource::collection($deliveryMan);
    }
}
