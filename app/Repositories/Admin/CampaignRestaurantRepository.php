<?php

namespace App\Repositories\Admin;

use App\Models\CampaignRestaurant;

class CampaignRestaurantRepository
{
    public function bulkInsert(int $campaignId, array $restaurantIds): void
    {
        $data = array_map(fn($restaurantId) => [
            'campaign_id' => $campaignId,
            'restaurant_id' => $restaurantId,
        ], $restaurantIds);

        CampaignRestaurant::insert($data);
    }

}