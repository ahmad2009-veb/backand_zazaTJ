<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $order;
    /**
     * Create a new job instance.
     * @param Order $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->order->delivery_man?->telegram_user_id) {
            $message = "Появился новый заказ по номеру №" . $this->order->id;
            $botToken = env('TELEGRAM_BOT_TOKEN');

            $inlineKeyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть', // Text for the button
                            'url'  => 'https://dashboard.air.tj' // Web app URL
                        ]
                    ]
                ]
            ];

            // Send the message to Telegram
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $this->order->delivery_man->telegram_user_id,
                'text' => $message,
                'reply_markup' => json_encode($inlineKeyboard),
            ]);
        }
    }
}
