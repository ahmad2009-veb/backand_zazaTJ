<?php

namespace App\Http\Controllers\Api\V3\Auth;

use App\CentralLogics\Helpers;
use App\CentralLogics\oson_sms;
use App\CentralLogics\SMS_module;
use App\CentralLogics\sms_module_v3;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ResetPasswordController extends Controller

{
    private  $oson_sms;


    public function __construct(oson_sms $oson_sms)
    {
        $this->oson_sms = $oson_sms;
    }

    public function reset_password_request(Request $request): JsonResponse
    {

         $request->validate([
            'phone' => 'required|min:13|max:13|exists:users,phone',
         ]);

        $customer = User::where('phone', $request['phone'])->first();

        if (isset($customer)) {
            if (env('APP_MODE') == 'demo') {
                return response()->json(['message' => trans('messages.otp_sent_successfully')]);
            }
            $token = rand(1000, 9999);

            DB::table('password_resets')->insert([
                'email' => 'null',
                'token' => $token,
                'phone' => $customer['phone'],
                'created_at' => now(),
            ]);

//            $response = sms_module_v3::send($request->input('phone'), $token);

            $response = $this->oson_sms->send_sms($request['phone'], $token);


            if ($response != 'success') {
                $errors = [];
                $errors[] = ['code' => 'otp', 'message' => trans('messages.faield_to_send_sms')];

                return response()->json([
                    'errors' => $errors,
                ], 405);
            }


            return response()->json(['otp' => $token, 'status' => true]);
        }

        return response()->json([
            'message' => 'Phone number not found!',
            'errors' => [
                ['code' => 'not-found', 'message' => 'Phone number not found!'],
            ],
        ], 404);


    }

    public function checkOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|size:13',
            'otp' => 'required|int|digits:4',
        ]);
        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json(['message' => 'Phone number not found'], 404);
        }
        $reset_data = DB::table('password_resets')->where([
            'phone' => $user->phone,
            'token' => $request->otp,
        ])->first();
        if ($reset_data) {
            return response()->json([
                'status' => 'true',
                'message' => 'Успешно подтвержден',
            ]);
        }

        return response()->json(['status' => 'false', 'message' => 'Invalid OTP.'], 400);
    }


    public function verify_otp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|min:13|max:13',
            'otp' => 'required'
        ]);


        $user = User::where('phone', $data['phone'])->first();
        if (!$user) {
            return response()->json(['message' => 'Phone number not found'], 404);
        }

        if (env('APP_MODE') == 'demo') {
            if ($request['reset_token'] == "1234") {
                return response()->json(['message' => "OTP found, you can proceed"], 200);
            }

            return response()->json([
                'errors' => [
                    ['code' => 'invalid', 'message' => 'Invalid OTP.'],
                ],
            ], 400);
        }
        $reset_data = DB::table('password_resets')->where([
            'phone' => $user->phone,
            'token' => $data['otp']
        ])->first();

        if ($reset_data) {
            return response()->json(['status' => 'ok'], 200);
        }

        return response()->json(['status' => 'false', 'message' => 'Invalid OTP.'], 400);

    }

    public function reset_password_submit(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|min:13|max:13|exists:users,phone',
            'otp' => 'required',
            'password' => 'required|min:8'
        ]);

        if (env('APP_MODE') == 'demo') {
            if ($data['otp'] == "1234") {
                DB::table('users')->where(['phone' => $data['phone']])->update([
                    'password' => bcrypt($data['password'])
                ]);
                return response()->json(['message' => 'Password changed successfully.'], 200);
            }

            return response()->json([
                'message' => 'Phone number and otp not matched!',
            ], 404);
        }

        $reset_data = DB::table('password_resets')->where(['token' => $data['otp']])->first();
        if ($reset_data) {
            User::where('phone', $data['phone'])->update([
                'password' => bcrypt($data['password']),
            ]);
            DB::table('password_resets')->where(['token' => $data['otp']])->delete();
            return response()->json(['message' => 'Password changed successfully.'], 200);

        }
        return response()->json([
            'message' => trans('messages.invalid_otp'),
            'errors' => [
                ['code' => 'invalid', 'message' => trans('messages.invalid_otp')],
            ],
        ], 400);


    }
}

