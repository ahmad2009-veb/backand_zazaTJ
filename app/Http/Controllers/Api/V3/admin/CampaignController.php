<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Models\Campaign;
use App\Models\CampaignRule;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\CampaignRestaurant;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Admin\CampaignService;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\Admin\CampaignResource;
use App\Http\Requests\Api\v3\CampaignStoreRequest;
use App\Http\Requests\Api\v3\admin\CampaignEditRequest;

class CampaignController extends Controller
{
    public function __construct
    (
        private CampaignService $campaignService
    ){}

    public function index(Request $request)
    {
        $campaigns = Campaign::query()->paginate($request->input('per_page', 10));
        return CampaignResource::collection($campaigns);
    }

    public function getCampaign(Campaign $campaign)
    {
        return $campaign->load('rule');
    }

    public function store(CampaignStoreRequest $request)
    {
        $campaign = $this->campaignService->createCampaign($request->validated());

        return response()->json([
            'message' => 'created successfully',
            'campaign' => $campaign,
        ], 201);
    }

    public function update(CampaignEditRequest $request, Campaign $campaign)
    {
        $this->campaignService->updateCampaign($campaign, $request);
        return response()->json(['message' => 'updated successfully'], 200);
    }

    public function updateStatus(Request $request, Campaign $campaign)
    {
        $campaign->status = $request->status;
        $campaign->save();
        return response()->json(['message' => 'updated successfully'], 200);
    }

    public function delete(Campaign $campaign)
    {
        if ($campaign->image) {

            Storage::disk('public')->delete('campaign/' . $campaign->image);

        }

        $campaign->delete();
        return response()->json(['message' => 'deleted successfully'], 200);
    }


    public function getCampaignRestaurants()
    {
        return DB::table('campaign_restaurant')->get();
    }



    public function campaignRestaurantDelete(Request $request)
    {
        DB::table('campaign_restaurant')->where('id', $request->campaignRestaurantId)->delete();

        return response()->json(['message' => 'deleted successfully'], 200);
    }

}
