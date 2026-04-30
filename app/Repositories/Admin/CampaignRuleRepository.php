<?php

namespace App\Repositories\Admin;

use App\Models\CampaignRule;

class CampaignRuleRepository
{
    public function store(string $ruleType, float $bonus, ?float $totalAmount = null): CampaignRule
    {
        $criteria = ['bonus' => $bonus];

        if ($ruleType === 'total_order') {
            $criteria['total_amount'] = $totalAmount;
        }

        return CampaignRule::create([
            'rule_type' => $ruleType,
            'criteria' => json_encode($criteria),
        ]);
    }
}