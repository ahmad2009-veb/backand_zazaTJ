<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Food;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Date;

class RestaurantService
{
    public function canOrder($startTime, $endTime)
    {
        if (!$endTime || !$startTime) {
            return true;
        }

        $now = Carbon::now()->format("H:i");

        if ($now < $startTime || $now >= $endTime) {

            return false;
        }

        $oneHourFromNow = Carbon::now()->addHour()->format('H:i');
        return $oneHourFromNow < $endTime;
    }

    public function isOpenedOnDeliveryTime($deliveryTime, $startTime, $endTime)
    {
        $givenDateTime = Carbon::parse($deliveryTime);
        $currentDateTime = Carbon::now();

        if(!$startTime && !$endTime) {

            return $givenDateTime >= $currentDateTime;
        }
        if($givenDateTime < $currentDateTime ) {
            return false;
        }
//        dd($deliveryTime);
        $stTime = Carbon::parse($startTime)->addHour()->format('H:i');
//        dd($parsedEndTime > $stTime);

        $deliveryDateTime = Carbon::parse($deliveryTime)->format('H:i');
        return $deliveryDateTime >= $stTime && $deliveryDateTime <= $endTime;
    }
}






