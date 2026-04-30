<?php

namespace App\Console;

use App\Console\Commands\celebrateBirthday;
use App\Console\Commands\LoyaltyPointsDayRemaining;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        celebrateBirthday::class,
        LoyaltyPointsDayRemaining::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
//        $schedule->command('inspire')->hourly();
        $schedule->command('send:birthday-message')->dailyAt('0:00');
        $schedule->command('send:loyaltyPointDayRemaining')->dailyAt('12:05');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
