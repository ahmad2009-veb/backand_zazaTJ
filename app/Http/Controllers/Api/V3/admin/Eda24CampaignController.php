<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignEda24Resource;
use App\Models\Campaign;
use App\Models\ItemCampaign;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Eda24CampaignController extends Controller
{

    public function list($type, Request $request)
    {
        if ($type == 'basic') {

            $campaigns = Campaign::latest()->paginate($request->input('per_page'));
        } else {
            $campaigns = ItemCampaign::latest()->paginate(config('default_pagination'));
        }

        return CampaignEda24Resource::collection($campaigns);
    }

    public function storeBasic(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|unique:campaigns|max:191',
            'description' => 'max:1000',
            'image' => 'required|image|mimes:jpg,jpeg,png',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'lang' => 'required|array',
            'lang.*' => 'required|string',
        ]);
        $campaign = new Campaign;
        $campaign->title = $request->title[array_search('en', $request->lang)];
        $campaign->description = $request->description[array_search('en', $request->lang)];
        $campaign->image = Helpers::upload('campaign/', 'png', $request->file('image'));
        $campaign->start_date = $request->start_date;
        $campaign->end_date = $request->end_date;
        $campaign->start_time = $request->start_time;
        $campaign->end_time = $request->end_time;
        $campaign->admin_id = auth('admin-api')->id();

        $campaign->save();
        $data = [];
        foreach ($request->lang as $index => $key) {
            if ($request->title[$index] && $key != 'en') {
                $data[] = [
                    'translationable_type' => 'App\Models\Campaign',
                    'translationable_id' => $campaign->id,
                    'locale' => $key,
                    'key' => 'title',
                    'value' => $request->title[$index],
                ];
            }
            if ($request->description[$index] && $key != 'en') {
                $data[] = [
                    'translationable_type' => 'App\Models\Campaign',
                    'translationable_id' => $campaign->id,
                    'locale' => $key,
                    'key' => 'description',
                    'value' => $request->description[$index],
                ];
            }
        }

        Translation::insert($data);

        return response()->json(['message' => 'Campaign created successfully'], 200);

    }
}
