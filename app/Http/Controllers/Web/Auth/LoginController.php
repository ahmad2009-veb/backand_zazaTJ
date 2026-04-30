<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Auth\CreateUserAction;
use App\Actions\Auth\SendConfirmationCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Auth\Traits\AuthenticationAttemptTrait;
use App\Http\Requests\Web\Auth\ConfirmCodeRequest;
use App\Http\Requests\Web\Auth\LoginRequest;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticationAttemptTrait;

    /**
     * @param \App\Actions\Auth\SendConfirmationCodeAction $sendConfirmationCodeAction
     * @param \App\Actions\Auth\CreateUserAction $createUserAction
     */
    public function __construct(
        private readonly SendConfirmationCodeAction $sendConfirmationCodeAction,
        private readonly CreateUserAction $createUserAction
    ) {
        $this->middleware(function ($request, $next) {
            if (auth()->check()) {
                return redirect('/');
            }

            return $next($request);
        });
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        return view('web.auth.login');
    }

    /**
     * @param \App\Http\Requests\Web\Auth\LoginRequest $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function login(LoginRequest $request)
    {
        if (!$this->isActive($request->phone)) {
            return redirect(route('login'))
                ->with('error', 'Этот номер заблокирован. Пожалуйста обратитесь в Колл-центр.');
        }

        if ($this->canSendCode($request->phone)) {
            $this->sendCode($request->phone);
        }

        $request->session()->put('auth.phone', $request->phone);

        $authenticationAttempt = $this->getAuthenticationAttempt($request->phone);
        if ($authenticationAttempt?->expire->addMinutes(3) < now()) {
           $authenticationAttempt->delete();
        }

        return redirect(route('auth.confirmation'));
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function confirmation(Request $request)
    {
        $phone = $request->session()->get('auth.phone');

        $authenticationAttempt = $this->getAuthenticationAttempt($phone);

        if ($authenticationAttempt?->expire->addMinutes(3) < now()) {
            $request->session()->forget('auth.phone');

            return redirect(route('login'));
        }

        return view('web.auth.confirmation', [
            'phone'         => $phone,
            'diffInSeconds' => $authenticationAttempt->expire > now() ? now()->diffInSeconds($authenticationAttempt->expire) : 0,
            'captcha'       => $authenticationAttempt->attemptsExceeded() ? $this->getCaptcha() : null,
        ]);
    }

    public function confirm(ConfirmCodeRequest $request)
    {
        $authenticationAttempt = $this->getAuthenticationAttempt();

        $user = $this->createUserAction->execute($authenticationAttempt);
        $user->update(['remember_token' => null]);

        auth()->login($user, true);

        $returnUrl = $request->session()->get('auth.previous.url', route('home'));

        $authenticationAttempt->delete();
        $request->session()->forget('auth');

        return redirect($returnUrl);
    }

    public function resend(Request $request)
    {
        if ($this->canResendCode()) {
            $phone = $request->session()->get('auth.phone');

            $this->sendCode($phone);
        }

        return redirect(route('auth.confirmation'));
    }

    private function getCaptcha()
    {
        $phraseBuilder = new PhraseBuilder(5, '0123456789');
        $captcha = new CaptchaBuilder(null, $phraseBuilder);
        $captcha->build();

        request()->session()->put('auth.captcha', $captcha->getPhrase());

        return $captcha;
    }
}
