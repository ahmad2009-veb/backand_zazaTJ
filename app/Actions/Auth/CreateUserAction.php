<?php

namespace App\Actions\Auth;

use App\Actions\BaseAction;
use App\CentralLogics\SMS_module;
use App\Models\AuthenticationAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction extends BaseAction
{
    /**
     * @param \App\Models\AuthenticationAttempt $authenticationAttempt
     * @return \App\Models\User
     */
    public function handle(AuthenticationAttempt $authenticationAttempt): User
    {
        $phone = "+992{$authenticationAttempt->phone}";

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['f_name' => $authenticationAttempt->phone, 'password' => Hash::make(uniqid())]
        );

        if ($user->wasRecentlyCreated) {
            SMS_module::send(
                $phone,
                'Спасибо за выбор службы доставки eda24.tj'
            );
        }

        return $user;
    }
}
