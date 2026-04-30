<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\CustomerPoint;
use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;

class LoyaltyPointController extends Controller
{
    public function index()
    {
        return LoyaltyPoint::where('status', 1)->get();
    }

    public function add_points_to_user(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'points' => 'required|numeric',
            'status' => 'required',
            'loyalty_point_id' => 'nullable|numeric'
        ]);

        $newPoint = CustomerPoint::query()->create($data);
        return $newPoint;
    }

    public function store(Request $request)
    {

        $data = $request->validate([
            'points' => 'required|numeric',
            'value' => 'required|numeric',
            'expires_at' => 'nullable|date',
            'description' => 'nullable|string'
        ]);

        return LoyaltyPoint::query()->create($data);
    }
}
