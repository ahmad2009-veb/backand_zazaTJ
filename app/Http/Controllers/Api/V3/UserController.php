<?php

namespace App\Http\Controllers\Api\V3;

use App\CentralLogics\Helpers;
use App\CentralLogics\sms_module_v3;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\UpdateProfileRequest;
use App\Http\Resources\CampaignsResourceCollection;
use App\Http\Resources\UserResource;
use App\Models\Campaign;
use App\Models\CustomerPoint;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return UserResource::make($user);
    }


    public function bonuses(Request $request)
    {
        $user = auth()->user();


        return [
            'points' => $user->loyalty_points ?? 0,
            'expires_at' => DB::table('loyalty_points')->first()->expires_at,
        ];


    }

    public function user_bonuses(User $user)
    {
        return [
            'points' => $user->loyalty_points ?? 0,
            'expires_at' => DB::table('loyalty_points')->first()->expires_at
        ];
    }


    public function campaigns(Request $request)
    {
        $user_campaigns = auth()->user()->campaigns;
        $activeCampaigns = Campaign::query()->active()->running()->get();
        $completed_status = $user_campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->campaign_id,
                'completed' => $campaign->completed
            ];
        });

        //Получение статуса акций пользователья с Id
        $idsAndCompletion = $completed_status->pluck('completed', 'id');

        //Добавление полей  completed  со статусом
        $activeCampaigns->transform(function ($item) use ($idsAndCompletion) {
            if ($idsAndCompletion->has($item['id'])) {
                $item['completed'] = $idsAndCompletion[$item['id']];
            }
            return $item;
        });
        return CampaignsResourceCollection::make($activeCampaigns);
    }

    public function update_profile(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $image = $user->image;
        if ($request->has('image')) {
            $image = $request->file('image');
            $image = Helpers::upload('profile/', $image->getClientOriginalExtension(), $request->file('image'));
            if ($user->image) {
                Storage::disk('public')->delete('profile/' . $user->image);
            }
        }

        $user->update([
            'f_name' => $request->input('name'),
            'birth_date' => $request->input('birth_date'),

            'password' => $request->input('password')
                ? Hash::make($request->input('password'))
                : $user->password,
            'image' => $image ?? $user->image,
        ]);

        if ($user->phone === $request->input('phone') && $request->input('password', '') === '') {
            return response()->json([
                'message' => 'Профиль успешно изменен',
            ]);
        } else {
            $otp = rand(1000, 9999);
            DB::table('phone_verifications')->updateOrInsert(['phone' => $request['phone']],
                [
                    'token' => $otp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            $response = sms_module_v3::send($request->input('phone'), $otp);


            if ($response != 'success') {
                $errors = [];
                $errors[] = ['code' => 'otp', 'message' => trans('messages.faield_to_send_sms')];

                return response()->json([
                    'errors' => $errors,
                ], 405);
            }
            return response()->json([

                'otp' => $otp
            ], 200);
        }

    }


    public function confirm_profile(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|min:13|max:13',
            'password' => 'nullable|min:8',
            'otp' => 'required|numeric'
        ]);

        $user = auth()->user();


        if ($user) {
            $verification_data = PhoneVerification::query()->where([
                'phone' => $data['phone'],
                'token' => $data['otp']
            ])->where('created_at', '>', now()->subMinutes(15))->first();

            if ($verification_data) {
                $user->phone = $data['phone'];
                if ($request->input('password')) {
                    $user->password = Hash::make($request->input('password'));
                }
                $user->save();
                $verification_data->delete();
                return response()->json(['message' => true], 201);

            } else {
                return response()->json(['message' => 'Неправильный код подтверждения'], 400);
            }
        }
    }

    public function userDeviceTokenStore(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string'
        ]);
        $user = auth()->user();
        $existingToken = UserDeviceToken::where('user_id', $user->id)->first();
        if ($existingToken) {
            if ($existingToken->device_token !== $request->device_token) {
                $existingToken->update(['device_token' => $request->device_token]);
                return response()->json(['message' => 'Device token updated successfully']);
            } else {
                return response()->json(['message' => 'Device token is up to date']);
            }
        } else {
            UserDeviceToken::query()->create([
                'user_id' => $user->id,
                'device_token' => $request->device_token
            ]);
        }
        return response()->json(['message' => 'Device token stored successfully']);
    }


    public function update_fcm_token(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string'
        ]);
        $user = Auth::user();
        $deviceToken = UserDeviceToken::query()->where('device_token', $request->device_token)->first();

        if ($deviceToken) {
            $deviceToken->delete();
        }

        $attributes = [
            'device_token' => $request->input('fcm_token'),
            'user_id' => $user->id,
        ];

        $values = [
            'device_token' => $request->input('fcm_token')
        ];

        $userDeviceToken = UserDeviceToken::query()->firstOrCreate($attributes, $values);

        return response()->json($userDeviceToken);
    }

    public function verifyDeleteAccount(Request $request)
    {

        $user = $request->user();
        $otp = rand(1000, 9999);
        DB::table('phone_verifications')->updateOrInsert(['phone' => $user->phone],
            [
                'token' => $otp,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $response = sms_module_v3::send($user->phone, $otp);
        if ($response != 'success') {
            $errors = [];
            $errors[] = ['code' => 'otp', 'message' => trans('messages.faield_to_send_sms')];

            return response()->json([
                'errors' => $errors,
            ], 405);
        }

        return response()->json([

            'message' => 'otp sent successfully',
            'otp' => $otp
        ]);
    }


    public function confirmDeleteAccount(Request $request)
    {
        $data = $request->validate([
            'otp' => 'required|numeric'
        ]);

        $user = auth()->user();

        $verification_data = PhoneVerification::query()->where([
            'phone' => $user->phone,
            'token' => $data['otp']
        ])->where('created_at', '>', now()->subMinutes(15))->first();


        if ($verification_data) {
            $user->phone = $user->phone . "-$user->id";
            $user->status = 0;

            $user->save();
            $user->delete();
            $verification_data->delete();
            $user->tokens()->delete();
            $userDeviceToken = UserDeviceToken::query()->where('user_id', $user->id);

            $userDeviceToken?->delete();

            return response()->json(['message' => true]);

        } else {
            return response()->json(['message' => 'Неправильный код подтверждения'], 400);
        }
    }


}


