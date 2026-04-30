<?php

namespace App\Console\Commands;

use App\Models\LoyaltyPoint;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging;

class LoyaltyPointsDayRemaining extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:loyaltyPointDayRemaining';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //Выбор пользователей с наличием бонусов

        $users = User::query()->where('loyalty_point', '>', 0)->get();

        //Собрать в кучу device tokens
        $userDeviceTokens = [];
        if ($users->count()) {
            $users->each(function ($user) use (&$userDeviceTokens) {
                $userTokens = $user->deviceTokens;
                $userTokens->each(function ($token) use (&$userDeviceTokens) {
                    $userDeviceTokens[] = $token->device_token;
                });
            });
        }

        //Вычисление срока дейстия бонусов
        $loyaltyPoint = LoyaltyPoint::query()->first();
        $expires_at = Carbon::parse($loyaltyPoint->expires_at);
        $now = Carbon::now();
        $daysLeft = $now->diffInDays($expires_at, false);

        // Рассылка уведомление об истечении срока действия бонусов
        if ($daysLeft < 4) {
            $notification = Notification::query()->where('type', 'bonus_expires')->first();
            $messaging = app('firebase.messaging');
            $notificationMessage = Messaging\Notification::fromArray([
                'title' => $notification['title'],
                'body' => $notification['description'],
                'image' => url('storage/notification/' . $notification['image'])
            ]);


            $message = CloudMessage::new()
                ->withNotification($notificationMessage);


            $sendReport = $messaging->sendMulticast($message, $userDeviceTokens);

            echo 'Successful sends: ' . $sendReport->successes()->count() . PHP_EOL;
            if ($sendReport->hasFailures()) {
                foreach ($sendReport->failures()->getItems() as $failure) {
                    echo $failure->error()->getMessage() . PHP_EOL;
                }
            }
            Log:info('job running');
            $this->info('Birthday messages sent successfully.');

        }




    }
}
