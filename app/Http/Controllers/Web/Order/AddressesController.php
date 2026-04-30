<?php

namespace App\Http\Controllers\Web\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Order\Address\StoreRequest;

class AddressesController extends Controller
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create()
    {
        return view('web.order.addresses.create');
    }

    /**
     * @param \App\Http\Requests\Web\Order\Address\StoreRequest $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(StoreRequest $request)
    {
        $address = auth()->user()->addresses()->create([
            'contact_person_name'   => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type'          => $request->address_type,
            'address'               => $request->address,
            'floor'                 => $request->floor,
            'road'                  => $request->road,
            'house'                 => $request->house,
            'longitude'             => $request->longitude,
            'latitude'              => $request->latitude,
            'zone_id'               => $request->zone_id,
        ]);

        return redirect(route('order.index', ['address' => $address->id]));
    }
}
