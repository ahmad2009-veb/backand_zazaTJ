<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\SMS_module;
use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\DeliveryManOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * OTP-авторизация для курьерского приложения (Flutter).
 *
 * Шаг 1: POST /api/v1/auth/delivery-man/send-otp    { phone }
 * Шаг 2: POST /api/v1/auth/delivery-man/verify-otp  { phone, otp }
 *          → возвращает { token, driver }
 */
class DeliveryManOtpController extends Controller
{
    // ----------------------------------------------------------------
    // POST /api/v1/auth/delivery-man/send-otp
    // ----------------------------------------------------------------
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:9|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $phone = $request->phone;

        // Курьер с таким телефоном должен существовать в системе
        $dm = DeliveryMan::where('phone', $phone)->first();
        if (!$dm) {
            return response()->json([
                'status'  => false,
                'message' => 'Курьер с таким номером не найден. Обратитесь к администратору.',
            ], 404);
        }

        // Генерируем OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Удаляем старые OTP для этого телефона
        DeliveryManOtp::where('phone', $phone)->delete();

        // Сохраняем новый OTP (действует 5 минут)
        DeliveryManOtp::create([
            'phone'      => $phone,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(5),
            'verified'   => false,
        ]);

        if (env('APP_MODE') !== 'demo') {
            $smsResponse = SMS_module::send($phone, $otp);
            if ($smsResponse !== 'success') {
                return response()->json([
                    'status' => false,
                    'message' => 'Не удалось отправить OTP по SMS.',
                ], 405);
            }
        }

        // В dev-режиме возвращаем OTP в ответе
        $data = ['status' => true, 'message' => 'OTP отправлен на номер ' . $phone];
        if (config('app.debug')) {
            $data['otp'] = $otp; // только в режиме отладки!
        }

        return response()->json($data, 200);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/delivery-man/verify-otp
    // ----------------------------------------------------------------
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $phone = $request->phone;
        $otp   = $request->otp;

        // Находим запись
        $record = DeliveryManOtp::where('phone', $phone)
            ->where('otp', $otp)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$record) {
            return response()->json(['status' => false, 'message' => 'Неверный OTP.'], 401);
        }

        if ($record->isExpired()) {
            return response()->json(['status' => false, 'message' => 'OTP истёк. Запросите новый.'], 401);
        }

        // Помечаем как использованный
        $record->update(['verified' => true]);

        // Находим курьера
        $dm = DeliveryMan::where('phone', $phone)->first();
        if (!$dm) {
            return response()->json(['status' => false, 'message' => 'Курьер не найден.'], 404);
        }

        // Обновляем auth_token (используется как Bearer в заголовках)
        $token         = Str::random(80);
        $dm->auth_token = $token;
        $dm->save();

        return response()->json([
            'status' => true,
            'token'  => $token,
            'driver' => [
                'id'     => $dm->id,
                'name'   => trim($dm->f_name . ' ' . $dm->l_name),
                'phone'  => $dm->phone,
                'email'  => $dm->email,
                'image'  => $dm->image,
                'active' => (bool) $dm->active,
            ],
        ], 200);
    }
}
