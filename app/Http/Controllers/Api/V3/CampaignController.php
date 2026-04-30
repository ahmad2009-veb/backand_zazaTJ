<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CampaignsResourceCollection;
use App\Http\Resources\CampaignsWithRestaurants;
use App\Http\Resources\CampaignsWithRestaurantsResourceCollection;
use App\Models\Campaign;
use App\Models\CampaignRule;
use App\Models\Promotion;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function getCampaigns()
    {
        $campaigns = Campaign::query()->active()->running()
            ->get();

        if (Auth::guard('api')->check()) {

            $user = Auth::guard('api')->user();
            $wishedRestaurants = Wishlist::query()->where('user_id', $user['id'])->pluck('restaurant_id')->toArray();


            return CampaignsWithRestaurantsResourceCollection::make($campaigns)->additional(['user_liked_restaurants' => $wishedRestaurants]);

        }


        return CampaignsWithRestaurantsResourceCollection::make($campaigns);

    }

    public function getActiveCampaignsWithPoints()
    {

        $user_campaigns = auth()->user()->campaigns;
        $activeCampaigns = Campaign::query()->active()->running()->get();
        $completed_status = $user_campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->campaign_id,
                'completed' => $campaign->completed
            ];
        });

        //Получение статуса акций пользователья с Id
        $idsAndCompletion = $completed_status->pluck('completed', 'id');

        //Добавление полей  completed  со статусом
        $activeCampaigns->transform(function ($item) use ($idsAndCompletion) {
            if ($idsAndCompletion->has($item['id'])) {
                $item['completed'] = $idsAndCompletion[$item['id']];
            }
            return $item;
        });
        return CampaignsResourceCollection::make($activeCampaigns);
    }

    public function getCampaignById(Campaign $campaign)
    {

        return CampaignResource::make($campaign->load('restaurants', 'items'));
    }

    public function pastCampaigns()
    {
        $user_campaigns = auth()->user()->campaigns;
        $pastCampaigns = Campaign::query()->pastCampaigns()->orderBy('created_at', 'desc')->get();
        $completed_status = $user_campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->campaign_id,
                'completed' => $campaign->completed
            ];
        });

        //Получение статуса акций пользователья с Id
        $idsAndCompletion = $completed_status->pluck('completed', 'id');

        $pastCampaigns->transform(function ($item) use ($idsAndCompletion) {
            if ($idsAndCompletion->has($item['id'])) {
                $item['completed'] = $idsAndCompletion[$item['id']];
            }
            return $item;
        });
        return response()->json([
            'data' => CampaignsResourceCollection::make($pastCampaigns),
        ]);
    }

    public function getCampaignWithRuleAndCompleteness(Request $request)
    {
        return Campaign::query()->active()->running()->whereHas('rule', function ($query) use ($request) {

            $query->where('rule_type', $request->q);
        })->with(['userCampaigns' => function ($q) {
            $q->where('user_id', auth()->user()->id);
        }])->first();
    }


}
