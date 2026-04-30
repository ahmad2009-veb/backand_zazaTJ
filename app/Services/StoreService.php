<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Food;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Date;

class StoreService
{
    public function canOrder($startTime, $endTime)
    {


        if (!$startTime || !$endTime) {
            return true;
        }

        // Get the current time and time one hour from now without formatting
        $now = Carbon::now();
        $oneHourFromNow = Carbon::now()->addHour();

        // Parse start and end times
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        // Adjust for cases where end time is past midnight (e.g., 0:00 or 23:59)
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        // Check if current time is within the allowed order window
        if ($now->lt($start) || $now->gte($end)) {
            return false;
        }

        // Ensure an order placed now would still be within the allowed time range in an hour
        return $oneHourFromNow->lt($end);
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






