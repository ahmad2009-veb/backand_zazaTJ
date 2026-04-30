<?php

namespace App\Http\Controllers\Api\V3\Auth;

use App\CentralLogics\oson_sms;
use App\CentralLogics\SMS_module;
use App\CentralLogics\sms_module_v3;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\CustomerLoginRequest;
use App\Http\Requests\Api\v3\CustomerRegisterRequest;
use App\Http\Requests\Api\v3\VerifyPhoneRequest;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\UserDeviceToken;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OsonSMS\OsonSMSService\OsonSmsService;

class CustomerAuthController extends Controller
{
    private $oson_sms;

    public function __construct(oson_sms $oson_sms)
    {
        $this->oson_sms = $oson_sms;
    }

    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|min:13|max:13|unique:users',
        ]);

        try {
            DB::beginTransaction();

//            User::create([
//                'f_name' => $data['name'],
//                'phone' => $data['phone'],
//                'password' => bcrypt($data['password']),
//            ]);
            $otp = rand(1000, 9999);
            DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                [
                    'token' => $otp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

//            $response = sms_module_v3::send($request['phone'], $otp);
            $response = $this->oson_sms->send_sms($request['phone'], $otp);

            if ($response != 'success') {
                $errors = [];
                $errors[] = ['code' => 'otp', 'message' => trans('messages.faield_to_send_sms')];

                return response()->json([
                    'errors' => $errors,
                ], 405);
            }

            DB::commit();

            return response()->json([

                'otp' => $otp
            ], 201);


        } catch (\Exception $exception) {
            DB::rollBack();
            return $exception->getMessage();
        }
    }


    public function verifyPhoneCreateUser(CustomerRegisterRequest $request)
    {
        $data = $request->validated();


        // Retrieve OTP verification data
        $verification_data = DB::table('phone_verifications')->where([
            'phone' => $data['phone'],
            'token' => $data['otp'],
        ])->first();

        if (!$verification_data) {
            return response()->json([
                'message' => 'Неверный одноразовый пароль!'
            ], 404);
        }

        $otpIsValid = now()->diffInMinutes($verification_data->created_at) < 1;


        if ($otpIsValid) {
            $newUser = User::create([
                'f_name' => $data['name'],
                'phone' => $data['phone'],
                'password' => bcrypt($data['password']),
                'is_phone_verified' => 1
            ]);

            $token = $newUser->createToken('RestaurantCustomerAuth')->accessToken;

            return response()->json([
                'user' => $newUser,
                'token' => $token,
                'message' => 'User created successfully',
            ], 201);

        }


        return response()->json([
            'message' => 'Ваш код устарел запросите новый.'
        ], 404);

    }


    public function login(CustomerLoginRequest $request)
    {
        $data = $request->validated();

        if (auth()->attempt($data)) {

            $token = auth()->user()->createToken('RestaurantCustomerAuth')->accessToken;
            $user = auth()->user();

            $primaryAddress = $user->addresses->first();

            if (!auth()->user()->status) {
                $errors = [];
                $errors[] = ['code' => 'auth-003', 'message' => trans('messages.your_account_is_blocked')];

                return response()->json([
                    'errors' => $errors,
                ], 403);
            }

            return response()->json(['token' => $token, 'user' => $user->setAttribute('primary_address', $primaryAddress)]);
        }
        return response()->json(['message' => 'Не авторизован или неверный пароль'], 401);
    }


    public function logout(Request $request)
    {
        $user = Auth::user();
        $userDeviceToken = UserDeviceToken::query()->where('device_token', $request->input('device_token'))->first();

        $userDeviceToken?->delete();

        $token = $request->user()->token();

        $token->revoke();

        return response()->json(['message' => 'true'], 200);

    }


}










