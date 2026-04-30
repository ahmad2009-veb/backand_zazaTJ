<?php

namespace App\Listeners;

use App\Events\CustomerPointStatus;
use App\Models\CampaignRule;
use App\Models\CustomerPoint;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class MakeCustomerPointStatus
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle(CustomerPointStatus $event)
    {
        if ($event->customerPoint->status == 1) {
            $customerPoint = $event->customerPoint;
            $completeCampaigns = CampaignRule::query()
                ->where(['rule_type' => 'complete_campaigns', 'status' => 1])->get();

            $completeCampaigns->each(function ($rule) use ($customerPoint) {

                $campaignIds = json_decode($rule->criteria)->campaigns;

                $customerPointsWithStatusTrue = CustomerPoint::query()
                    ->whereIn('campaign_id', $campaignIds)->where([
                        'user_id' => $customerPoint->user_id,
                        'status' => 1,
                    ])->get();

                if (count($campaignIds) == $customerPointsWithStatusTrue->count()) {

                    $custPointWithNull = CustomerPoint::query()->where([
                        'user_id' => $customerPoint->user_id,
                        'order_id' => null,
                        'status' => 'pending'
                    ])->get();

                    $custPointWithNull->each(function ($item) use ($campaignIds ,$customerPointsWithStatusTrue) {
                        $campaign = $item->campaign;
                        $campRule = $campaign->rule;
                        $ruleCriteria = $campRule->criteria;
                        $campIds = json_decode($ruleCriteria)->campaigns ;

                        $result = $this->arrayExistsInArray($campIds,$customerPointsWithStatusTrue->pluck('campaign_id')->toArray() );

                        if($result) {
                            $item->status = 1 ;
                            $item->save();
                            $user = User::find($item->user_id);
                            $user->loyalty_point += json_decode($ruleCriteria)->bonus;
                            $user->save();
                        }
                    });
                }
            });
        }

    }

    public function arrayExistsInArray($needle, $haystack) {
        // Ensure both parameters are arrays
        if (!is_array($needle) || !is_array($haystack)) {
            return false;
        }

        // Check if every element in $needle exists in $haystack
        foreach ($needle as $item) {
            if (!in_array($item, $haystack)) {
                return false;
            }
        }

        return true;
    }

}
