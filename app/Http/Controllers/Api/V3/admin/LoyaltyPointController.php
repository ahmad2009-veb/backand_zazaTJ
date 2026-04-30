<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GetUserResource;
use App\Http\Resources\Admin\SpentPointsResource;
use App\Models\SpentPoints;
use App\Models\User;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

class LoyaltyPointController extends Controller
{
    public function index(Request $request)
    {

        $search = $request->input('search');
        $keywords = explode(' ', $search);

        $spentPoints = SpentPoints::query()->with('user')
            ->when(count($keywords) > 0, function ($query) use ($keywords) {
                $query->where(function ($query) use ($keywords) {
                    foreach ($keywords as $value) {
                        $query->orWhere('user_id', 'like', "%{$value}%")
                            ->orWhereHas('user', function ($query) use ($value) {
                                $query->where('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('phone', 'like', "%{$value}%");
                            });
                    }
                });
            })
            ->paginate($request->input('per_page', 15));
        return SpentPointsResource::collection($spentPoints);

    }

    public function exportData()
    {
        $spentPoints = SpentPoints::all();
        return (new FastExcel($this->exportSpentPoints($spentPoints)))->download('SpentPoints.xlsx');
    }

    private function exportSpentPoints($spentPoints)
    {
        $storage = [];
        foreach ($spentPoints as $item) {
            $storage[] = [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'order_detail_id' => $item->order_detail_id,
                'points' => $item->points,
                'source' => $item->source,
                'order_id' => $item->order_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ];
        }


        return $storage;
    }

    public function addPoint(Request $request, User $user)
    {
        $user->loyalty_points += $request->input('points', 0);
        $user->save();
        return response()->json(['message' => 'added successfully'], 201);
    }

    public function getUsers(Request $request) {
        $search = $request->input('search', '');
       $keywords = explode(' ', $search);

        $users = User::query()->when(count($keywords) > 0, function($query) use ($keywords) {
            $query->where(function ($query) use ($keywords) {
                foreach ($keywords as $value) {
                    $query->orWhere('id', 'like', "%{$value}%")
                       ->orWhere('f_name', 'like',"%{$value}%")
                        ->orWhere('l_name', 'like',"%{$value}%" )
                        ->orWhere('phone', 'like', "%{$value}%");
                }
            });
        })->paginate($request->input('per_page', 10));

        return GetUserResource::collection($users);
    }
}
