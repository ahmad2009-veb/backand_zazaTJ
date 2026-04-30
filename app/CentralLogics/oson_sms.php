<?php

namespace App\CentralLogics;

use Illuminate\Support\Facades\Log;
use OsonSMS\OsonSMSService\OsonSmsService;

class oson_sms
{

    protected $oson_sms_service;

    public function __construct(OsonSmsService $osonSmsService)
    {
        $this->oson_sms_service = $osonSmsService;
    }

    public function send_sms($receiver, $otp)
    {


        try {

            $this->oson_sms_service->sendSMS(
                senderName: config('osonsmsservice.sender_name'),
                phonenumber: $receiver,
                message: $otp,
                txnId: rand(1000000000, 9999999999),
            );

            return 'success';

        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return 0;
        }


    }
}
