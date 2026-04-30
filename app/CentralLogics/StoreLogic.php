<?php

namespace App\CentralLogics;

use App\Models\OrderTransaction;
use App\Models\Restaurant;
use App\Models\Wishlist;

class StoreLogic
{
    public static function calculate_store_rating($ratings): array
    {
        $total_submit = $ratings[0] + $ratings[1] + $ratings[2] + $ratings[3] + $ratings[4];
        $rating = ($ratings[0] * 5 + $ratings[1] * 4 + $ratings[2] * 3 + $ratings[3] * 2 + $ratings[4]) / ($total_submit ? $total_submit : 1);

        return ['rating' => $rating, 'total' => $total_submit];
    }



}
