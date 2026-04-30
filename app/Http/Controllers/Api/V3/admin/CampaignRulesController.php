<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\CreatCampaignItemDetailRequest;
use App\Http\Requests\Api\v3\admin\StoreCampaignItemsRequest;
use App\Http\Requests\Api\v3\CampaignRuleStoreRequest;
use App\Http\Resources\Admin\CampaignItemsDetailsResource;
use App\Http\Resources\Admin\CampaignRulesResource;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\CampaignItemDetail;
use App\Models\CampaignRule;
use Illuminate\Http\Request;

class CampaignRulesController extends Controller
{
    public function index(Request $request)
    {
        $campaignRules = CampaignRule::query()->paginate($request->input('per_page'));
        return CampaignRulesResource::collection($campaignRules);
    }


    public function updateStatus(Request $request, CampaignRule $campaignRule)
    {
        $campaignRule->status = $request->status;
        $campaignRule->save();

        return response()->json([
            'message' => 'Status updated'
        ]);
    }

    public function addCampaignItem(StoreCampaignItemsRequest $request, Campaign $campaign)
    {
        $items = collect($request->input('items'));
        try {

            $items->each(function ($item) use ($campaign) {
                $newItem = new CampaignItem();
                $newItem->campaign_id = $campaign->id;
                $newItem->food_id = $item['id'];
                $newItem->quantity = $item['qty'];
                $newItem->variant = $item['variant'];
                $newItem->add_ons = json_encode($item['add_ons']);
                $newItem->save();
            });



            return response()->json(['message' => 'items added successfully'], 201);
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' '. $ex->getLine();
        }


    }








    public function getItemsByCamp(Request $request, Campaign $campaign)
    {

        $itemsDetails = $campaign->campaignItems()->paginate($request->input('per_page', 10));
        return CampaignItemsDetailsResource::collection($itemsDetails);
    }

    public function delete(CampaignRule $campaignRule)
    {
        if ($campaignRule->status == 0) {
            $campaignRule->delete();
            return response()->json(['message' => 'deleted successfully'], 201);
        }

        return response()->json(['message' => 'Deleting not allowed'], 403);
    }

}
