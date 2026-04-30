<?php

namespace App\CentralLogics;

use App\Models\BusinessSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use OsonSMS\OsonSMSService\OsonSmsService;

class sms_module_v3
{
    public static function send($receiver, $message)
    {
        $provider = strtolower((string) config('sms.provider', 'generic'));

        if (in_array($provider, ['osonsms', 'oson_sms', 'oson'], true)) {
            return self::oson($receiver, $message);
        }

        $config = self::get_settings('log_sms');
        if (isset($config) && $config['status'] == 1) {
            self::log($receiver, $message);
        }
        $config = self::get_settings('payvand_sms');
        if (isset($config) && $config['status'] == 1) {
            return self::payvand($receiver, $message);
        }
        return 'not_found';
    }

    protected static function oson($receiver, $message)
    {
        try {
            /** @var OsonSmsService $osonSmsService */
            $osonSmsService = app(OsonSmsService::class);
            $osonSmsService->sendSMS(
                senderName: config('osonsmsservice.sender_name'),
                phonenumber: $receiver,
                message: (string) $message,
                txnId: rand(1000000000, 9999999999),
            );

            return 'success';
        } catch (\Throwable $exception) {
            Log::error('Oson SMS send failed', [
                'receiver' => $receiver,
                'error' => $exception->getMessage(),
            ]);

            return 'error';
        }
    }

    public static function get_settings($name)
    {
        $config = null;
        $data = BusinessSetting::where(['key' => $name])->first();
        if (isset($data)) {
            $config = json_decode($data['value'], true);
            if (is_null($config)) {
                $config = $data['value'];
            }
        }
        return $config;
    }


    public static function log($receiver, $message)
    {
        logger("[SMS | Log]:", [
            'receiver' => $receiver,
            'message' => $message,
        ]);
    }

    public static function payvand($receiver, $message)
    {
        $config = self::get_settings('payvand_sms');
        $response = 'error';

        if (isset($config) && $config['status'] == 1) {
            $message = (strlen($message) == 4 && is_int($message))
                ? str_replace("#OTP#", $message, $config['otp_template'])
                : $message;
            $endpoint = $config['endpoint'];
            $token = $config['token'];
            $source_address = $config['source_address'];

            $headers = [
                "Content-Type: application/json",
                "Api-Key: {$token}",
                "Locale: EN",
            ];

            $messages = [
                [
                    'source-address' => $source_address,
                    'destination-address' => $receiver,
                    'txn-id' => rand(1000000000, 9999999999),
                    'message' => $message,
                ],
            ];


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $data = curl_exec($ch);
            if ($data === false) {
                $error_msg = curl_error($ch);
                curl_close($ch);

                logger("[SMS | Payment] cURL Error:", [
                    'error' => $error_msg,
                ]);
            } else {
                curl_close($ch);

                logger("[SMS | Payment] Response:", [
                    'response' => $data,
                ]);

                $response_data = json_decode($data, true);
                if (isset($response_data['msg']) && $response_data['msg'] == 'Success') {
                    $response = 'success';
                } else {
                    $response = 'failure';
                    logger("[SMS | Payment] Failure Response:", [
                        'response' => $response_data,
                    ]);
                }
            }

            return $response;
        }
    }
}







