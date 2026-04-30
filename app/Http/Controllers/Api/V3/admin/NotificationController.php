<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\NotificationRequest;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Kreait\Firebase\Exception\MessagingException;

class NotificationController extends Controller
{
    public NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function store(NotificationRequest $request)
    {
        if (config('app.mode') == 'demo') {
            return response()->json([
                'errors' => 'feature-disable', 'This option is disabled for demo!',
            ], 404);
        }
        if ($request->has('image')) {
            $image_name = Helpers::upload('notification/', 'png', $request->file('image'));

        } else {
            $image_name = null;
        }
        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'tergat' => $request->tergat,
            'image' => $image_name,
            'zone_id' => $request->zone_id
        ];

        $newNotification = $this->notificationService->store($data);

        try {
            $this->notificationService->sendNotificationToTopic($newNotification, $data['tergat']);
            return response()->json(['message' => 'Notification sent successfully'], 200);

        } catch (MessagingException $e) {
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine()]);
        }


    }


    public function update(NotificationRequest $request, Notification $notification)
    {


        if ($request->has('image')) {
            $image_name = Helpers::upload('notification/', 'png', $request->file('image'));
            Storage::disk('public')->delete('notification/' . $notification->image);
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'tergat' => $request->tergat,
            'image' => $image_name ?? $notification->image,
            'zone_id' => $request->zone_id

        ];
        $updatedNotification = $this->notificationService->update($notification, $data);

        try {
            $this->notificationService->sendNotificationToTopic($updatedNotification, $data['tergat']);
            return response()->json(['message' => 'Notification sent successfully'], 200);

        } catch (MessagingException $e) {
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine()]);

        }
    }


    public function delete(Notification $notification)
    {
        Storage::disk('public')->delete('notification/' . $notification->image);
        $notification->delete();

        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
