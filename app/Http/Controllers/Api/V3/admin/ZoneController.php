<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class ZoneController extends Controller
{
    public function index()
    {
        return response()->json(Zone::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                    => 'required|unique:zones|max:191',
            'coordinates'             => 'required',
            'per_km_delivery_charge'  => 'required_with:minimum_delivery_charge',
            'minimum_delivery_charge' => 'required_with:per_km_delivery_charge',
        ]);

        $value = $request->coordinates;
        foreach (explode('),(', trim($value, '()')) as $index => $single_array) {
            if ($index == 0) {
                $lastcord = explode(',', $single_array);
            }
            $coords    = explode(',', $single_array);
            $polygon[] = new Point($coords[0], $coords[1]);
        }
        $zone_id                       = Zone::all()->count() + 1;
        $polygon[]                     = new Point($lastcord[0], $lastcord[1]);
        $zone                          = new Zone();
        $zone->name                    = $request->name;
        $zone->coordinates             = new Polygon([new LineString($polygon)]);
        $zone->restaurant_wise_topic   = 'zone_' . $zone_id . '_restaurant';
        $zone->customer_wise_topic     = 'zone_' . $zone_id . '_customer';
        $zone->deliveryman_wise_topic  = 'zone_' . $zone_id . '_delivery_man';
        $zone->per_km_shipping_charge  = $request->per_km_delivery_charge;
        $zone->minimum_shipping_charge = $request->minimum_delivery_charge;
        $zone->save();
    }
}
