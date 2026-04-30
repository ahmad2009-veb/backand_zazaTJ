<?php

namespace App\Http\Controllers\Web\Auth\Traits;

use App\Actions\Auth\SendConfirmationCodeAction;
use App\Models\AuthenticationAttempt;
use App\Models\User;

trait AuthenticationAttemptTrait
{
    private function getAuthenticationAttempt(?string $phone = null)
    {
        return AuthenticationAttempt::firstWhere(
            'phone',
            $phone ?? request()->session()->get('auth.phone')
        );
    }

    private function canSendCode(string $phone)
    {
        $authenticationAttempt = $this->getAuthenticationAttempt($phone);

        if (!$authenticationAttempt) {
            return true;
        }

        if ($authenticationAttempt->expire < now()) {
            return !$authenticationAttempt->attemptsExceeded();
        }

        return false;
    }

    private function canResendCode()
    {
        $authenticationAttempt = $this->getAuthenticationAttempt();

        if (!$authenticationAttempt) {
            return false;
        }

        if ($authenticationAttempt->expire > now()) {
            return false;
        }

        return true;
    }

    private function sendCode(string $phone)
    {
        $expire = now()->addSeconds(120);

        $attempt = AuthenticationAttempt::firstOrCreate(
            [
                'phone' => $phone,
            ],
            [
                'attempts'   => 0,
                'expire'     => $expire,
                'user_agent' => request()->header('User-Agent'),
                'ip'         => request()->ip(),
            ]
        );

        $user = $this->getUserByPhone($phone);
        $code = $user?->fixed_code ?? app(SendConfirmationCodeAction::class)->execute($phone);

        $attempt->update([
            'code'   => $code,
            'expire' => $expire,
        ]);
    }

    private function isActive(string $phone): bool
    {
        $user = $this->getUserByPhone($phone);
        if (!$user) {
            return true;
        }

        return $user->status == 1;
    }

    private function getUserByPhone(string $phone): ?User
    {
        return User::firstWhere('phone', "+992{$phone}");
    }
}
