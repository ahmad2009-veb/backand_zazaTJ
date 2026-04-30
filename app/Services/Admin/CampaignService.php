<?php

namespace App\Services\Admin;

use App\Models\Campaign;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\v3\admin\CampaignEditRequest;
use App\Repositories\Admin\{CampaignRepository, CampaignRuleRepository, CampaignRestaurantRepository};

class CampaignService
{
    public function __construct(
        private CampaignRepository $campaignRepository,
        private CampaignRuleRepository $campaignRuleRepository,
        private CampaignRestaurantRepository $campaignRestaurantRepository
    ) {}

    public function createCampaign(array $data): Campaign
    {
        return DB::transaction(function () use ($data) {
        // Upload image if available
            $image = isset($data['image']) 
                ? Helpers::upload('campaign/', 'png', $data['image']) 
                : null;

            // Create campaign rule
            $rule = $this->campaignRuleRepository->store(
                ruleType: $data['rule_type'],
                bonus: $data['criteria_bonus'],
                totalAmount: $data['criteria_total_amount'] ?? null
            );

            // Create campaign
            $campaign = $this->campaignRepository->store([
                'title' => $data['title'],
                'description' => $data['description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'image' => $image,
                'campaign_rule_id' => $rule->id,
                'admin_id' => auth()->id(),
            ]);

            // Attach restaurants
            $this->campaignRestaurantRepository->bulkInsert($campaign->id, $data['restaurant_ids']);

            return $campaign->load('rule');
        });
    }


    public function updateCampaign(Campaign $campaign, CampaignEditRequest $request): Campaign
    {
        $data = $request->only([
            'title',
            'description',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = Helpers::upload('campaign/', 'png', $request->file('image'));
        }

        return $this->campaignRepository->update($campaign, $data);
    }
}