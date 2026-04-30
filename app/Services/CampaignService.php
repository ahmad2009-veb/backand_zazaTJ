<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRule;
use App\Models\CustomerPoint;
use App\Models\UserCampaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{

    public function handleCampaign($data, $order_amount, $orderId)
    {
        $user = Auth::guard('api')->user();
        Log::debug('ddd');
        if (isset($data['campaign_id'])) {
            $campaign = Campaign::query()->where('id', '=', $data['campaign_id'])
                ->whereHas('restaurants', function ($query) use ($data) {
                    $query->where('restaurants.id', '=', $data['restaurant_id']);
                })
                ->active()
                ->running()->first();

            if ($campaign == null) return;

            $campaignRules = CampaignRule::query()->where('status', '=', 1)->get();
//            dd($campaign->rule);
            //Динамачески вызывает метод  предназначеный для акции
            if (method_exists($this, $campaign->rule->rule_type)) {
                $this->{$campaign->rule->rule_type}($campaignRules
                    ->where('rule_type', $campaign->rule->rule_type)->flatten()->first(), $order_amount, $user, $campaign->id, $orderId);
            }
        } else {
            $campaigns = Campaign::query()->leftJoin('campaign_restaurant', 'campaigns.id', '=', 'campaign_restaurant.campaign_id')
                ->select('campaigns.*', 'restaurant_id')
                ->active()
                ->running()
                ->where('restaurant_id', $data['restaurant_id'])
                ->get();
//            dd($campaigns);

            if ($campaigns->isEmpty()) {
                return;
            }

            $campaigns->each(function ($campaign) use ($user, $order_amount, $orderId) {

                if ($campaign->rule['rule_type'] == "total_order") {
                    $this->total_order($campaign->rule, $order_amount, $user, $campaign->id, $orderId);
                }
            });
        }


    }


    public function combo($campaignRule, $order_amount, $user, $campaignId, $orderId): void
    {
        //Проверка на повторное выполнение акции, если акция выполнена то выходим из функции
        $userCamp = UserCampaign::where([
            'user_id' => $user->id,
            'campaign_id' => $campaignId,
            'completed' => 100
        ])->first();
        if ($userCamp) {
            return;
        }

        $criteria = json_decode($campaignRule->criteria, true);
        $bonus = collect($criteria['bonus'])->first();

        $user_campaign = UserCampaign::query()->updateOrCreate([
            'user_id' => $user->id,
            'campaign_id' => $campaignId
        ], [
            'completion_date' => now(),
            'completed' => $this->calc_percent(null, null),
        ]);
//        dd($user_campaign);
        if ($user_campaign->completed == 100) {

            $loyaltyPoint = DB::table("loyalty_points")->where('status', 1)->first();
            $points = CustomerPoint::query()->updateOrCreate([
                'user_id' => $user->id,
                'points' => $bonus,
                'campaign_id' => $campaignId,
                'loyalty_point_id' => $loyaltyPoint->id,
                'order_id' => $orderId,
            ]);
            $this->complete_campaigns($user_campaign, $user);
        }


        $user_campaign->save();
    }

    public function total_order($campaignRule, $order_amount, $user, $campaignId, $orderId): void
    {
        //Проверка на повторное выполнение акции, если акция уже выполнена то выходим из функции
        $userCamp = UserCampaign::query()->where([
            'user_id' => $user->id,
            'campaign_id' => $campaignId,
            'completed' => 100
        ])->first();

        if ($userCamp) {
            return;
        }

        $criteria = json_decode($campaignRule->criteria, true);
        $bonus = $criteria['bonus'];

        $user_campaign = UserCampaign::query()->where('campaign_id', $campaignId)
            ->where('user_id', $user->id)
            ->first();


        $new_completed = $this->calc_percent($order_amount, $criteria['total_amount']);

        if ($user_campaign) {
            $user_campaign->update([
                'completed' => $user_campaign->completed + $new_completed
            ]);
            if ($user_campaign->completed >= 100) {
                $user_campaign->completion_date = now();
                $user_campaign->completed = 100;
                $user_campaign->save();

                $loyaltyPoint = DB::table("loyalty_points")->where('status', 1)->first();
                $points = CustomerPoint::query()->updateOrCreate([
                    'user_id' => $user->id,
                    'points' => $bonus,
                    'campaign_id' => $campaignId,
                    'loyalty_point_id' => $loyaltyPoint->id,
                    'order_id' => $orderId,
                ]);

                $this->complete_campaigns($user_campaign, $user);
            }
        } else {
            $new_campaign = UserCampaign::query()->create([
                'campaign_id' => $campaignId,
                'user_id' => $user->id,
                'completion_date' => null,
                'completed' => $new_completed
            ]);
        }
    }


    public function complete_campaigns($userCampaign, $user)
    {
        // Выборка акций типа complete_campaigns
        $complete_campaigns = Campaign::query()->active()->running()->whereHas('rule', function ($query) {
            $query->where('rule_type', 'complete_campaigns');
        })->get();

        //Итерация акций
        $complete_campaigns->each(function ($item) use ($userCampaign, $user) {

            $campaignIds = json_decode($item->rule->criteria)->campaigns;
            collect($campaignIds)->each(function ($value) use ($userCampaign, $item, $campaignIds, $user) {

                if ($userCampaign->campaign_id === $value) {
//                    dd($userCampaign->campaign_id);

                    $newUserCamp = UserCampaign::query()->firstOrNew([
                        'user_id' => auth()->user()->id,
                        'campaign_id' => $item->id,

                    ],
                    );
                    $loyaltyPoint = DB::table("loyalty_points")->where('status', 1)->first();

                    $criteria = json_decode($item->rule->criteria, true);
                    $newUserCamp->completed += 100 / count($campaignIds);
                    if ($newUserCamp->completed >= 100) {
                        $newUserCamp->completed = 100;
                        $newUserCamp->completion_date = now();

                        CustomerPoint::query()->create([
                            'user_id' => $user->id,
                            'points' => $criteria['bonus'],
                            'campaign_id' => $item->id,
                            'loyalty_point_id' => $loyaltyPoint->id,
                        ]);
                    }

                    $newUserCamp->save();

                }
            });
        });
    }


    public function calc_percent($part, $total): int
    {
        if (!$part && !$total) {
            return 100;
        }
        $perc = ($part / $total) * 100;

        return $perc;
    }


}
