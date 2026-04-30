<?php

namespace App\Contracts\Services;

interface SmsProvider
{
    /**
     * @param string $phone
     * @param string $message
     * @return void
     */
    public function send(string $phone, string $message): void;
}
