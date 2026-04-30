<?php

namespace App\Http\Requests\Web\Auth;

use App\Http\Controllers\Web\Auth\Traits\AuthenticationAttemptTrait;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmCodeRequest extends FormRequest
{
    use AuthenticationAttemptTrait;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code'    => ['required', 'digits:4'],
            'captcha' => $this->captchaRules(),
        ];
    }

    public function messages()
    {
        return [
            'code.required'    => 'Введите код подтверждения',
            'code.digits'      => 'Код должен состоять из 4 цифр',
            'captcha.required' => 'Введите защитный код',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->failed()) {
                return;
            }

            $failed                = false;
            $captcha               = $this->session()->get('auth.captcha');
            $authenticationAttempt = $this->getAuthenticationAttempt();

            if ($authenticationAttempt->attemptsExceeded() && $this->captcha != $captcha) {
                $failed = true;
                $validator->errors()->add('captcha', 'Неверный защитный код');
            }

            if ($authenticationAttempt->code != $this->code) {
                $failed = true;
                $validator->errors()->add('code', 'Неверный код');
            }

            if ($failed) {
                $authenticationAttempt->increment('attempts');
            }
        });
    }

    private function captchaRules(): array
    {
        $authenticationAttempt = $this->getAuthenticationAttempt();

        return [$authenticationAttempt?->attemptsExceeded() ? 'required' : 'nullable'];
    }
}
