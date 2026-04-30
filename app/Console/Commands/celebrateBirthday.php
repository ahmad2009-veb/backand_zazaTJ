<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\RewardPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging;

class celebrateBirthday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:birthday-message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send birthday notification';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $today = Carbon::today()->format('m-d');
        $users = User::query()->whereRaw('DATE_FORMAT(birth_date, "%m-%d") = ?', [$today])->get();
        $notification = Notification::query()->where('type', '=', 'birthday')->first();
        $rewardPoint = RewardPoint::query()->where('type', 'birthday')->first();
        $userDeviceTokens = [];


        $users->each(function (User $user) use (&$userDeviceTokens, $rewardPoint) {
            $userTokens = $user->deviceTokens;
            $userTokens->each(function ($token) use (&$userDeviceTokens) {
                $userDeviceTokens[] = $token->device_token;
            });

            $user->loyalty_point += $rewardPoint->points;
            $user->save();
        });



        $messaging = app('firebase.messaging');
        $notificationMessage = Messaging\Notification::fromArray([
            'title' => $notification['title'],
            'body' => $notification['description'] . $rewardPoint->points . ' баллов',
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
