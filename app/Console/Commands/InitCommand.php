<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'application:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import initial database.sql';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Artisan::call('db:wipe', ['--force' => true]);
        $sql_path = base_path('installation/database.sql');
        DB::unprepared(file_get_contents($sql_path));
    }
}
