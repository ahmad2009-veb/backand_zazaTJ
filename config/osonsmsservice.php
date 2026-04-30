<?php

/*
 * You can register for FREE in https://osonsms.com in order to get access to OsonSMS gateway and send SMS via its gateway.
 */
return [
    'login' => env('SMS_LOGIN', ''),
    'pass_salt_hash' => env('SMS_TOKEN', ''),
    'sender_name' => env('SMS_SENDER', ''),
    'server_url' => env('SMS_SERVER', 'https://api.osonsms.com/sendsms_v1.php'),
];
