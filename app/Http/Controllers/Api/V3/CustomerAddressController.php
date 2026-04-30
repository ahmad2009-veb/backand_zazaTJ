<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\CustomerAddressStoreRequest;
use App\Http\Requests\Api\v3\CustomerAddressUpdateRequest;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class CustomerAddressController extends Controller
{
    public function store(CustomerAddressStoreRequest $request)
    {
        $data = $request->validated();

        $customer = auth()->user();

        $newAddress = CustomerAddress::create([
            'user_id' => $customer->id,
            'road' => $data['street'] ?? null,
            'house' => $data['house'] ?? null,
            'apartment' => $data['apartment'] ?? null,
            'domofon_code' => $data['domofon_code'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'address_type' => 'home',
            'contact_person_number' => $customer->phone,
        ]);
        return $newAddress;

    }

    public function customer_addresses()
    {
        return CustomerAddress::where('user_id', auth()->user()->id)->get();
    }

    public function update(CustomerAddressUpdateRequest $request, $id)
    {

        try {
            $address = CustomerAddress::where('id', $id)->where('user_id', auth()->user()->id)->firstOrFail();
            $address->update([
                'road' => $request['street'],
                'house' => $request['house'],
                'apartment' => $request['apartment'],
                'domofon_code' => $request['domofon_code'],
                'longitude' => $request['longitude'],
                'latitude' => $request['latitude'],
                'address_type' => 'home',
                'contact_person_number' => auth()->user()['phone']
            ]);
            return response()->json(['message' => ' current address updated successfully'], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Address not found'], 404);
        }


    }
}
