<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class OptionsController extends Controller
{
    public function stores()
    {
        $stores =  Store::active()->select('id', 'name')->get()->map(function ($el) {
            return ['id' => $el->id, 'name' => $el->name];
        });

        return response()->json(['data' => $stores]);
    }
}
