<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\StoreBannerRequest;
use App\Http\Requests\Api\v3\admin\UpdateBannerRequest;
use App\Http\Resources\Admin\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $banners = Banner::latest()->paginate($request->per_page ?? 12);

        return BannerResource::collection($banners);
    }

    public function store(StoreBannerRequest $request)
    {
        $banner = new Banner;
        $banner->title = $request->title;
        $banner->type = 'restaurant_wise';
//        $banner->zone_id = $request->zone_id;
        $banner->image = Helpers::upload('banner/', 'png', $request->file('image'));
        $banner->data = $request->store_id;
        $banner->status = 1;
        $banner->save();

        return response()->json([
            'message' => 'Banner created successfully'
        ], 200);
    }

    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $banner->title = $request->title;
//        $banner->zone_id = $request->zone_id;
        if ($request->hasFile('image')) {
            Storage::disk('public')->delete('banner/' . $banner->image);
            $banner->image = Helpers::upload('banner/', 'png', $request->file('image'));
        }
        $banner->data = $request->store_id;
        $banner->save();

        return response()->json([
            'message' => 'Banner updated successfully'
        ], 200);
    }

    public function status(Request $request, $banner)
    {
        $banner = Banner::find($banner);
        $banner->status = $request->status;
        $banner->save();

        return response()->json([
            'message' => 'Banner status updated successfully'
        ], 200);
    }

    public function destroy(Banner $banner)
    {
        Storage::disk('public')->delete('banner/' . $banner->image);
        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted successfully'
        ], 200);
    }
}
