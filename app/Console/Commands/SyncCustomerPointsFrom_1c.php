<?php

namespace App\Console\Commands;

use App\Models\CustomerPoint;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class SyncCustomerPointsFrom_1c extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync_customer_points_1c';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronization customer points with 1c ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $url = 'http://127.0.0.1:8001/api/users/points';
        $client = new Client();

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Content-Type' => 'application/json; charset = utf-8',
                ],

            ]);

            $response = json_decode($response->getBody(), true);

            $customer_points = $response['data'];
            $bar = $this->output->createProgressBar(count($customer_points));
            if (!empty($customer_points)) {
                $bar->start();
            }

            collect($customer_points)->each(function ($el) use ($bar) {
                $localCustomer = User::query()->find($el['user_id']);
                if (isset($localCustomer)) {
                    dd($localCustomer);
                }

                $bar->advance();
            });


            $bar->finish();

        } catch (GuzzleException $e) {

        }
    }
}
