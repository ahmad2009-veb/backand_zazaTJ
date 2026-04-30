<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\SMS_module;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Phone-OTP login for the ZAZA Customer App.
 *
 * Flow:
 *   1. POST auth/customer/send-otp   → find-or-create user, write 6-digit OTP to
 *                                       phone_verifications, return code in debug mode.
 *   2. POST auth/customer/verify-otp → verify code, return Passport access_token + user.
 */
class CustomerOtpController extends Controller
{
    private const OTP_TTL_MINUTES = 5;

    // ─────────────────────────────────────────────────────────────────────
    // SEND OTP
    // ─────────────────────────────────────────────────────────────────────
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:7|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $phone = $request->phone;

        // Find or create a minimal user record
        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'f_name'   => 'User',
                'l_name'   => $phone,
                'email'    => $phone . '@placeholder.zaza',
                'password' => bcrypt(Str::random(32)),
                'status'   => 1,
                'is_phone_verified' => 0,
            ]
        );

        // Generate & store 6-digit OTP
        $otp = rand(100000, 999999);

        DB::table('phone_verifications')->updateOrInsert(
            ['phone' => $phone],
            [
                'token'      => $otp,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (env('APP_MODE') !== 'demo') {
            $smsResponse = SMS_module::send($phone, $otp);
            if ($smsResponse !== 'success') {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to send OTP SMS.',
                ], 405);
            }
        }

        $response = [
            'status'  => true,
            'message' => 'OTP sent to ' . $phone,
        ];

        // Return OTP in response when debug mode is on (dev only)
        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response, 200);
    }

    // ─────────────────────────────────────────────────────────────────────
    // VERIFY OTP
    // ─────────────────────────────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $phone = $request->phone;
        $otp   = $request->otp;

        // Check OTP (within TTL window)
        $record = DB::table('phone_verifications')
            ->where('phone', $phone)
            ->where('token', $otp)
            ->where('updated_at', '>=', now()->subMinutes(self::OTP_TTL_MINUTES))
            ->first();

        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid or expired OTP.',
            ], 401);
        }

        // Consume the OTP
        DB::table('phone_verifications')->where('phone', $phone)->delete();

        // Find user (was created in sendOtp)
        $user = User::where('phone', $phone)->first();

        if (!$user || !$user->status) {
            return response()->json([
                'status'  => false,
                'message' => 'Account not found or disabled.',
            ], 404);
        }

        // Mark phone verified
        $user->is_phone_verified = 1;
        $user->save();

        // Issue Passport access token
        $token = $user->createToken('ZazaCustomerApp')->accessToken;

        return response()->json([
            'status' => true,
            'token'  => $token,
            'user'   => [
                'id'     => $user->id,
                'name'   => trim($user->f_name . ' ' . $user->l_name),
                'phone'  => $user->phone,
                'email'  => $user->email,
                'image'  => $user->image ?? '',
                'wallet' => $user->wallet_balance ?? 0,
            ],
        ], 200);
    }
}
