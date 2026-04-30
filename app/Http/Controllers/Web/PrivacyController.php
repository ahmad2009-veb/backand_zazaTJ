<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function privacy()
    {
        return view('web.privacy');
    }

    public function deleteAccount()
    {
        return view('web.delete-account');
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'reason' => 'nullable|string',
        ]);
        $reason = $request->input('reason');

        $message = "
*Заявка на удаления аккаунта*

*Имя:* $request->name,
*Номер телефона:* $request->phone,
";

        if (isset($reason)) {
            $message .= "*Причина:* $request->reason";
        }

        $client = new Client();
        $url = env('TELEGRAM_API_URL') . env('TELEGRAM_BOT_API') . '/sendMessage';

        $client->post($url, [
            'form_params' => [
                'chat_id' => env('TELEGRAM_CHAT_ID'),
                'text' => $message,
                'parse_mode' => 'Markdown',
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Заявка успешно отправлена',
        ]);
    }

    public function sendFeedback(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'text' => 'nullable|string',
        ]);
        $reason = $request->input('text');

        $message = "
*Feedback*

*Имя:* $request->name,
*Номер телефона:* $request->phone,
";

        if (isset($reason)) {
            $message .= "*Текст:* $reason";
        }

        $client = new Client();
        $url = env('TELEGRAM_API_URL') . env('TELEGRAM_BOT_API') . '/sendMessage';

        $client->post($url, [
            'form_params' => [
                'chat_id' => env('TELEGRAM_CHAT_ID'),
                'text' => $message,
                'parse_mode' => 'Markdown',
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Заявка успешно отправлена',
        ]);
    }

}
