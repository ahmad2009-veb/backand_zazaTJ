<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\NotificationRequest;
use App\Http\Resources\NotificationItemResource;
use App\Http\Resources\NotificationResourceCollection;
use App\Http\Resources\OrderNotificationItemResource;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;

use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class CustomerNotificationController extends Controller
{

    public function getUserNotifications(Request $request)
    {
        $user_notifications = UserNotification::query()->where('user_id', auth()->user()->id)->orderBy('id', 'desc')->paginate($request->per_page);
        return OrderNotificationItemResource::collection($user_notifications);

    }

    public function system_notifications(Request $request) {
        return NotificationItemResource::collection(Notification::orderBy('id', 'desc')->paginate($request->per_page));
    }



}
