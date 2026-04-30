<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TransactionSchedule;

class CleanupTransactionSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedules:cleanup {--days=30 : Number of days to keep approved dates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old approved dates from transaction schedules';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $keepDays = (int) $this->option('days');
        
        $this->info("Cleaning up approved dates older than {$keepDays} days...");
        
        $schedules = TransactionSchedule::whereNotNull('approved_dates')->get();
        $totalCleaned = 0;
        $schedulesAffected = 0;
        
        foreach ($schedules as $schedule) {
            $cleaned = $schedule->cleanOldApprovedDates($keepDays);
            if ($cleaned > 0) {
                $totalCleaned += $cleaned;
                $schedulesAffected++;
            }
        }
        
        $this->info("Cleanup completed!");
        $this->info("- Schedules affected: {$schedulesAffected}");
        $this->info("- Total dates cleaned: {$totalCleaned}");
        
        return Command::SUCCESS;
    }
}
