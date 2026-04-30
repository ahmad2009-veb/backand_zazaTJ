<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;


class NotificationService
{

    public function __construct()
    {
    }

    public function store($data)
    {
        $notification = new Notification();
        return $this->extracted($data, $notification);
    }


    public function update($notification, $data)
    {
        return $this->extracted($data, $notification);
    }


    public function extracted($data, $notification): mixed
    {
        $notification->title = $data['title'];
        $notification->description = $data['description'];
        $notification->tergat = $data['tergat'];
        $notification->image = $data['image'];
        $notification->zone_id = $data['zone_id'];
        $notification->save();

        return $notification;
    }

    public function sendNotificationToTopic($notification, $topic)
    {
        $messaging = app('firebase.messaging');

        $message = Messaging\Notification::fromArray([
            'title' => $notification['title'],
            'body' => $notification['description'],
            'image' => url('storage/notification/' . $notification['image'])
        ]);


        $message = CloudMessage::withTarget('topic', $topic)
            ->withNotification($message)
            ->withData(['notification_id' => $notification->id]);

        $messaging->send($message);
    }


    public function sendFirebaseOrderStatusNotification($status, $order)
    {
        // Выборка активного уведомления из бизнесс настроек

        $notification = BusinessSetting::query()->where('key', $status)->first();

        if (!$notification) {
            return;
        }
        $notificationValue = json_decode($notification->value, true);
        $notificationStatus = $notificationValue['status'] ?? 0;
        //        Если статус 1 то отправлям уведомление
        if ($notificationStatus != 1) {
            return;
        }
        //        Поиск пользователя заказа
        $user = $order->customer;
        //        Сбор девайс токенов
        $deviceTokens = $user->deviceTokens;

        if ($deviceTokens->isEmpty()) {
            return;
        }
        $tokens = $deviceTokens->pluck('device_token')->toArray();
//        Рассылка уведомления

        $messaging = app('firebase.messaging');

        $notificationMessage = Messaging\Notification::fromArray([
            'title' => 'Заказ №' . $order->id,
            'body' => json_decode($notification->value, true)['message'],
        ]);


        $message = CloudMessage::new()
            ->withNotification($notificationMessage);


        $sendReport = $messaging->sendMulticast($message, $tokens);

        echo 'Successful sends: ' . $sendReport->successes()->count() . PHP_EOL;
        if ($sendReport->hasFailures()) {
            foreach ($sendReport->failures()->getItems() as $failure) {
                echo $failure->error()->getMessage() . PHP_EOL;
            }
        }


    }

    public function send_firebase_notification_to_device($token, $data)
    {
        $delivery_firebase = Firebase::project('delivery_notification');

        $messaging = $delivery_firebase->messaging();

//        $token = 'cGVfo5N6QfWfdxriN-YDet:APA91bFF7ES0JqCocAJ9EovuT-ORyuq8J33z8pG6qCy2a6WRMaNbOGsrdIdc8IHVXRDPhb_6gBzkzx8pbTwSUMQLICF0gFECdDEEv95DJ-GEIfrkwcmfyrTqtcd4yi_yGVufP96JDVY9';
        $message = $data['message'] ?? '';
        $conversation_id = $data['conversation_id'] ?? '';
        $sender_type = $data['sender_type'] ?? '';
        $notification_data = [
            'title' => $data['title'] ?? '',
            'body' => $data['description'] ?? '',
            'image' => $data['image'] ?? '',
            'order_id' => $data['order_id'] ?? '',
            'conversation_id' => $conversation_id,
            'sender_type' => $sender_type,
            'type' => $data['type'] ?? '',
            'is_read' => 0
        ];

        $notification = Messaging\Notification::fromArray([
            'title' => $data['title'],
            'body' => $data['description'],
            'image' => $data['image'],
        ]);

        $config = Messaging\AndroidConfig::fromArray([
            'ttl' => '3600s',

            'notification' => [
                'title_loc_key' => (string)$data['order_id'],
                'body_loc_key' => $data['type'],
                'icon' => 'new',
                'sound' => 'notification.wav',
                "channel_id" => "stackfood",
            ]

        ]);
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData($notification_data)
            ->withAndroidConfig($config);


        try {
            $messaging->send($message);
        } catch (MessagingException $e) {
            echo $e->getMessage();
            print_r($e->errors());
        }
    }
}
















