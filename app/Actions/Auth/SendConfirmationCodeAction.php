<?php

namespace App\Actions\Auth;

use App\Actions\BaseAction;
use App\CentralLogics\SMS_module;

class SendConfirmationCodeAction extends BaseAction
{
    /**
     * @param string $phone
     * @return int
     */
    public function handle(string $phone): int
    {
        $code = rand(1000, 9999);

        SMS_module::send($phone, $code);

        return $code;
    }
}
