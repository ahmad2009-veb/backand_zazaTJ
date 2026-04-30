<?php

namespace Database\Seeders;

use App\Models\CampaignRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CampaignRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        CampaignRule::query()->create([
            'rule_type' => 'combo',
            'criteria' => json_encode(["bonus" => 300]),
        ]);

        CampaignRule::query()->create([
            'rule_type' => 'total_order',
            'criteria' => json_encode(["bonus" => 300, "total_amount" => 500]),
        ]);

        CampaignRule::query()->create([
            'rule_type' => 'complete_campaigns',
            'criteria' => json_encode(["bonus" => 200, "campaigns" => [1, 2]])
        ]);


    }
}
