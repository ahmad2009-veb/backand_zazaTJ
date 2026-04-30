<?php

namespace App\Repositories\Admin;

use App\Models\Campaign;
use Illuminate\Support\Facades\Storage;

class CampaignRepository
{
    public function store(array $data): Campaign
    {
        return Campaign::create($data);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (isset($data['image'])) {
            // Delete old image
            if ($campaign->image) {
                Storage::disk('public')->delete('campaign/' . $campaign->image);
            }
            $campaign->image = $data['image'];
        }

        $campaign->fill([
            'title' => $data['title'] ?? $campaign->title,
            'description' => $data['description'] ?? $campaign->description,
            'start_date' => $data['start_date'] ?? $campaign->start_date,
            'end_date' => $data['end_date'] ?? $campaign->end_date,
            'start_time' => $data['start_time'] ?? $campaign->start_time,
            'end_time' => $data['end_time'] ?? $campaign->end_time,
        ])->save();

        return $campaign;
    }
}